<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Engagement\Actions\SaveCommitment;
use App\Modules\Engagement\Actions\SetCommitmentStatus;
use App\Modules\Engagement\Data\CommitmentData;
use App\Modules\Engagement\Enums\CommitmentGroup;
use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\SurveyClock;
use App\Modules\Organization\Models\Area;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(SaveCommitment::class, SetCommitmentStatus::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
    workIn($this->tenant);
    $this->area = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->small = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    // 12 answers in one area, 8 in another and 3 in a third: the last is under the minimum and is not shown.
    $this->survey = climateResultsSurvey($this->tenant, 12, null, ['area_id' => $this->area->id]);
    $other = Area::factory()->create(['tenant_id' => $this->tenant->id]);
    foreach ([[$other, 8], [$this->small, 3]] as [$area, $count]) {
        App\Modules\Engagement\Models\Response::factory()->count($count)->create(['tenant_id' => $this->tenant->id, 'survey_id' => $this->survey->id, 'area_id' => $area->id]);
    }
});

function commitmentData(Survey $survey, int $userId, CommitmentGroup $group = CommitmentGroup::Company, ?string $groupId = null, string $willDo = 'Presentar las metas en una junta por área.', ?CarbonImmutable $dueOn = null): CommitmentData
{
    return new CommitmentData($survey->id, $userId, $group, $groupId, null, 'Que las metas no se explican.', $willDo, 'Ana Torres', $dueOn ?? CarbonImmutable::now()->addMonth());
}

it('saves a commitment for the company on a closed survey, logging it without the texts', function (): void {
    Log::spy();

    $saved = resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, willDo: 'Texto secreto de la acción'));

    expect($saved->group_type)->toBe(CommitmentGroup::Company)
        ->and($saved->status)->toBe(CommitmentStatus::Open)
        ->and($saved->will_do)->toBe('Texto secreto de la acción');
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context): bool => $message === 'engagement.commitment_saved' && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secreto'))->once();
    $entry = AuditEntry::query()->where('event', 'engagement.commitment_saved')->sole();
    expect(json_encode($entry->metadata, JSON_THROW_ON_ERROR))->not->toContain('secreto')->not->toContain('metas');
});

it('saves a commitment for a group the results show', function (): void {
    $saved = resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, CommitmentGroup::Area, $this->area->id));

    expect($saved->group_id)->toBe($this->area->id);
});

it('rejects a group the results hide, one that does not exist and a group id on the company', function (): void {
    $save = fn (CommitmentGroup $group, ?string $id) => fn () => resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, $group, $id));

    expect($save(CommitmentGroup::Area, $this->small->id))->toThrow(ValidationException::class)
        ->and($save(CommitmentGroup::Area, '3bdc0e57-8a35-4e0c-83a8-3a4f4c0c8a10'))->toThrow(ValidationException::class)
        ->and($save(CommitmentGroup::Area, null))->toThrow(ValidationException::class)
        ->and($save(CommitmentGroup::Company, $this->area->id))->toThrow(ValidationException::class);
    expect(Commitment::query()->count())->toBe(0);
});

it('accepts a third commitment for a group and rejects the fourth, and a dropped one frees a place', function (): void {
    $save = fn () => resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, CommitmentGroup::Area, $this->area->id));
    $first = $save();
    $save();
    $save();

    expect($save(...))->toThrow(ValidationException::class);
    expect(Commitment::query()->count())->toBe(3);

    resolve(SetCommitmentStatus::class)->handle($first->id, CommitmentStatus::Dropped);
    $save();
    expect(Commitment::query()->count())->toBe(4)
        ->and($save(...))->toThrow(ValidationException::class);
});

it('counts the commitments of each group on its own', function (): void {
    foreach (range(1, 3) as $ignored) {
        resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id));
    }

    expect(resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, CommitmentGroup::Area, $this->area->id)))->toBeInstanceOf(Commitment::class);
});

it('does not bring back a dropped commitment when its group is full', function (): void {
    $save = fn () => resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id));
    $dropped = $save();
    resolve(SetCommitmentStatus::class)->handle($dropped->id, CommitmentStatus::Dropped);
    $save();
    $save();
    $save();

    expect(fn () => resolve(SetCommitmentStatus::class)->handle($dropped->id, CommitmentStatus::Open))->toThrow(ValidationException::class);
    expect($dropped->fresh()?->status)->toBe(CommitmentStatus::Dropped);
});

it('only takes commitments on a closed survey', function (): void {
    $active = Survey::factory()->active()->pulse()->create(['tenant_id' => $this->tenant->id]);

    expect(fn () => resolve(SaveCommitment::class)->handle(commitmentData($active, $this->hr->id)))->toThrow(ValidationException::class);
    expect(Commitment::query()->count())->toBe(0);
});

it('does not accept a due date before today when creating, and allows today', function (): void {
    expect(fn () => resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, dueOn: SurveyClock::day()->subDay())))->toThrow(ValidationException::class);

    $today = resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, dueOn: SurveyClock::day()));
    expect($today->due_on->toDateString())->toBe(SurveyClock::day()->toDateString());
});

it('edits the texts, owner and date of a commitment but never its group, and may keep a date already past', function (): void {
    $saved = resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, CommitmentGroup::Area, $this->area->id));
    $this->travel(2)->months();

    $edited = resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id, CommitmentGroup::Company, null, 'Otra acción', $saved->due_on), $saved->id);

    expect($edited->id)->toBe($saved->id)
        ->and($edited->will_do)->toBe('Otra acción')
        ->and($edited->group_type)->toBe(CommitmentGroup::Area)
        ->and($edited->group_id)->toBe($this->area->id);
});

it('changes the status and logs it without the texts', function (): void {
    $saved = resolve(SaveCommitment::class)->handle(commitmentData($this->survey, $this->hr->id));

    $done = resolve(SetCommitmentStatus::class)->handle($saved->id, CommitmentStatus::Done);

    expect($done->status)->toBe(CommitmentStatus::Done);
    expect(AuditEntry::query()->where('event', 'engagement.commitment_status_changed')->count())->toBe(1);
});
