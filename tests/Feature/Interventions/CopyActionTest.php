<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CopyAction;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Http\Controllers\ActionController;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

mutates(CopyAction::class, ActionController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    [$this->campaign, $this->areas] = closedEvaluationInAreas($this->tenant, ['Producción' => 7, 'Ventas' => 6]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->items = ProgramItem::query()->where('program_id', $this->program?->id)->orderBy('scope_key')->get();
    $this->source = ProgramAction::factory()->inProgress()->create([
        'tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->items[0]->id,
        'subjects' => 'areas', 'responsible_name' => 'Laura Méndez', 'starts_on' => '2026-12-01', 'due_on' => '2026-12-20',
    ]);
    ActionArea::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $this->source->id, 'area_id' => $this->areas['Producción']->id]);
    ProgressEntry::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $this->source->id]);
});

it('copies an action to other rows as independent actions, planned and with no history', function (): void {
    $copies = resolve(CopyAction::class)->handle($this->source->id, [$this->items[1]->id, $this->items[2]->id], $this->hr->id);

    expect($copies)->toHaveCount(2)
        ->and(ProgramAction::query()->count())->toBe(3);

    foreach ($copies as $index => $copy) {
        expect($copy->id)->not->toBe($this->source->id)
            ->and($copy->item_id)->toBe($this->items[$index + 1]->id)
            ->and($copy->status)->toBe(ActionStatus::Planned)
            ->and($copy->completed_on)->toBeNull()
            ->and($copy->title)->toBe($this->source->title)
            ->and($copy->description)->toBe($this->source->description)
            ->and($copy->kind)->toBe($this->source->kind)
            ->and($copy->level)->toBe($this->source->level)
            ->and($copy->responsible_name)->toBe('Laura Méndez')
            ->and($copy->starts_on?->toDateString())->toBe('2026-12-01')
            ->and($copy->due_on->toDateString())->toBe('2026-12-20')
            ->and($copy->created_by)->toBe($this->hr->id)
            ->and(ActionArea::query()->where('action_id', $copy->id)->pluck('area_id')->all())->toBe([$this->areas['Producción']->id])
            ->and(ProgressEntry::query()->where('action_id', $copy->id)->count())->toBe(0);
    }

    expect($this->source->fresh()?->status)->toBe(ActionStatus::InProgress);
});

it('copies a third-level action with its professional', function (): void {
    $clinical = ProgramAction::factory()->clinical()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->items[0]->id]);

    $copy = resolve(CopyAction::class)->handle($clinical->id, [$this->items[1]->id], $this->hr->id)[0];

    expect($copy->kind)->toBe(ActionKind::ClinicalCare)
        ->and($copy->professional?->value)->toBe('psychologist');
});

it('refuses to copy to its own row, to a row of another Programa or to none', function (): void {
    [$second] = closedEvaluationInAreas($this->tenant, ['Logística' => 6]);
    $secondProgram = resolve(CreateProgram::class)->handle($second->id, null);
    $foreignRow = ProgramItem::query()->where('program_id', $secondProgram?->id)->firstOrFail();

    foreach ([[$this->items[0]->id], [$this->items[1]->id, $this->items[0]->id], [], [$this->items[1]->id, $this->items[1]->id]] as $ids) {
        expect(fn () => resolve(CopyAction::class)->handle($this->source->id, $ids, $this->hr->id))->toThrow(ValidationException::class);
    }

    expect(fn () => resolve(CopyAction::class)->handle($this->source->id, [$foreignRow->id], $this->hr->id))->toThrow(ModelNotFoundException::class)
        ->and(ProgramAction::query()->count())->toBe(1);
});

it('copies all or none: an area no longer shown refuses the whole copy', function (): void {
    // Only Producción and Ventas are shown; point the source at an area that is not among the shown ones.
    $hidden = App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Compras']);
    ActionArea::query()->where('action_id', $this->source->id)->update(['area_id' => $hidden->id]);

    expect(fn () => resolve(CopyAction::class)->handle($this->source->id, [$this->items[1]->id, $this->items[2]->id], $this->hr->id))->toThrow(ValidationException::class)
        ->and(ProgramAction::query()->count())->toBe(1);
});

it('does not find the action of another company', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $foreign = ProgramAction::factory()->create(['tenant_id' => $other->id]);
    workIn($this->tenant);

    expect(fn () => resolve(CopyAction::class)->handle($foreign->id, [$this->items[1]->id], $this->hr->id))->toThrow(ModelNotFoundException::class);
});

it('logs the copy with ids and a count', function (): void {
    Log::spy();

    resolve(CopyAction::class)->handle($this->source->id, [$this->items[1]->id, $this->items[2]->id], $this->hr->id);

    Log::shouldHaveReceived('info')->with('intervention.action_copied', ['action_id' => $this->source->id, 'program_id' => $this->program?->id, 'copies' => 2])->once();
});

it('copies from the page', function (): void {
    $url = route('tenant.campaigns.program.actions.copy', [$this->tenant, $this->campaign->id, $this->source->id]);

    $this->actingAs($this->hr)->post($url, ['item_ids' => [$this->items[1]->id]])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]));
    expect(ProgramAction::query()->count())->toBe(2);

    $this->actingAs($this->hr)->post($url, ['item_ids' => []])->assertSessionHasErrors('item_ids');
    $this->actingAs($this->hr)->post($url, [])->assertSessionHasErrors('item_ids');
    $this->actingAs($this->hr)->post($url, ['item_ids' => ['x']])->assertSessionHasErrors('item_ids.0');
    $this->actingAs($this->hr)->post($url, ['item_ids' => [$this->items[0]->id]])->assertSessionHasErrors('item_ids');
});

it('lets only owner and RH copy, and finds no action of another company', function (): void {
    $url = route('tenant.campaigns.program.actions.copy', [$this->tenant, $this->campaign->id, $this->source->id]);
    $other = Tenant::factory()->create();
    workIn($other);
    $foreign = ProgramAction::factory()->create(['tenant_id' => $other->id]);
    workIn($this->tenant);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post($url, ['item_ids' => [$this->items[1]->id]])->assertForbidden();
    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->post($url, ['item_ids' => [$this->items[1]->id]])->assertForbidden();
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.actions.copy', [$this->tenant, $this->campaign->id, $foreign->id]), ['item_ids' => [$this->items[1]->id]])->assertNotFound();

    expect(ProgramAction::query()->count())->toBe(1);
});

it('tells what the Tabla asks and what is covered, to everyone who reads the Programa', function (): void {
    $viewer = memberOf($this->tenant, TenantRole::Viewer);

    $this->actingAs($viewer)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))->assertInertia(fn (Assert $page) => $page
        ->where('program.requirements', fn ($lines) => collect($lines)->pluck('key')->all() === ['policy_review', 'policy_dissemination', 'analysis', 'awareness_campaign', 'specific_assessment']
            && collect($lines)->firstWhere('key', 'policy_review')['status'] === 'covered'
            && collect($lines)->firstWhere('key', 'awareness_campaign')['status'] === 'missing'
            && collect($lines)->firstWhere('key', 'specific_assessment')['status'] === 'missing'));
});

it('does not count a cancelled action toward what the Tabla asks', function (): void {
    $this->source->update(['status' => 'cancelled', 'cancelled_reason' => 'Ya no aplica.']);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))->assertInertia(fn (Assert $page) => $page
        ->where('program.requirements', fn ($lines) => collect($lines)->firstWhere('key', 'policy_review')['status'] === 'missing'));
});
