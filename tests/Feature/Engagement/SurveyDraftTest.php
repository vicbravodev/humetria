<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Engagement\Actions\CreateSurveyDraft;
use App\Modules\Engagement\Actions\DeleteSurveyDraft;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Exceptions\SurveyStateChanged;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

mutates(CreateSurveyDraft::class, DeleteSurveyDraft::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-09 15:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
    workIn($this->tenant);
});

it('creates a climate draft with the first-wave questionnaire', function (): void {
    $survey = resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);

    expect($survey->status)->toBe(SurveyStatus::Draft)
        ->and($survey->kind)->toBe(SurveyKind::Climate)
        ->and($survey->bank_version)->toBe('v1')
        ->and($survey->items)->toHaveCount(44)
        ->and($survey->items)->not->toContain('loop.action@1')
        ->and($survey->name)->toBe('Clima · noviembre 2026')
        ->and($survey->created_by)->toBe($this->user->id)
        ->and($survey->closes_on)->toBeNull();
});

it('adds the closing-the-loop question once a survey has been closed before', function (): void {
    Survey::factory()->closed()->create(['tenant_id' => $this->tenant->id, 'kind' => SurveyKind::Pulse]);

    $survey = resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);

    expect($survey->items)->toHaveCount(45)->and($survey->items)->toContain('loop.action@1');
});

it('names the draft after the local month, not the UTC one', function (): void {
    Carbon::setTestNow('2026-12-01 03:00:00');

    $survey = resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);

    expect($survey->name)->toBe('Clima · noviembre 2026');
});

it('allows one open climate survey per company', function (): void {
    resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);

    expect(fn () => resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('lets another company have its own draft, and a closed survey make way for a new one', function (): void {
    resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);
    workIn(Tenant::factory()->create());

    resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);

    expect(Survey::query()->count())->toBe(1);

    $other = Tenant::factory()->create();
    Survey::factory()->closed()->create(['tenant_id' => $other->id]);
    workIn($other);
    resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);

    expect(Survey::query()->count())->toBe(2);
});

it('deletes a draft', function (): void {
    $survey = resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);

    resolve(DeleteSurveyDraft::class)->handle($survey->id);

    expect(Survey::query()->count())->toBe(0);
});

it('never deletes a survey that was launched', function (): void {
    $survey = Survey::factory()->active()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => resolve(DeleteSurveyDraft::class)->handle($survey->id))->toThrow(SurveyStateChanged::class)
        ->and(Survey::query()->count())->toBe(1);
});

it('writes a log line for each action, with ids and no names', function (): void {
    Log::spy();

    $survey = resolve(CreateSurveyDraft::class)->handle(SurveyKind::Climate, $this->user->id);
    resolve(DeleteSurveyDraft::class)->handle($survey->id);

    Log::shouldHaveReceived('info')->with('engagement.survey_drafted', ['survey_id' => $survey->id, 'kind' => 'climate'])->once();
    Log::shouldHaveReceived('info')->with('engagement.survey_draft_deleted', ['survey_id' => $survey->id])->once();
});

it('lets whoever has the permission of the kind view and manage, and nobody else', function (): void {
    $survey = Survey::factory()->create(['tenant_id' => $this->tenant->id]);
    $hr = memberOf($this->tenant, TenantRole::Hr);
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    expect($hr->can('view', $survey))->toBeTrue()
        ->and($hr->can('manage', $survey))->toBeTrue()
        ->and($viewer->can('view', $survey))->toBeTrue()
        ->and($viewer->can('manage', $survey))->toBeFalse()
        ->and(User::factory()->create()->can('view', $survey))->toBeFalse();
});

it('never stores a launched survey without its dates', function (): void {
    expect(fn () => Survey::factory()->create(['tenant_id' => $this->tenant->id, 'status' => SurveyStatus::Active, 'launched_at' => null]))
        ->toThrow(QueryException::class);
});
