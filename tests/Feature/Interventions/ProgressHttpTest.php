<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Http\Controllers\ProgressController;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Interventions\Support\ProgramPages;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ProgressController::class, ProgramPages::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    [$this->campaign] = closedEvaluationInAreas($this->tenant, ['Producción' => 7]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->item = ProgramItem::query()->where('program_id', $this->program?->id)->where('scope', 'final')->sole();
    $this->action = ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id, 'due_on' => '2026-12-15']);
});

function progressUrl(object $test, ?ProgramAction $action = null): string
{
    return route('tenant.campaigns.program.progress.store', [$test->tenant, $test->campaign->id, ($action ?? $test->action)->id]);
}

function cancelUrl(object $test, ?ProgramAction $action = null): string
{
    return route('tenant.campaigns.program.actions.cancel', [$test->tenant, $test->campaign->id, ($action ?? $test->action)->id]);
}

it('records a step and goes back to the Programa', function (): void {
    $this->actingAs($this->hr)->post(progressUrl($this), ['status' => 'in_progress', 'recorded_on' => '2026-11-19', 'note' => 'Arrancó.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]));

    expect($this->action->fresh()?->status)->toBe(ActionStatus::InProgress)
        ->and(ProgressEntry::query()->sole()->recorded_by)->toBe($this->hr->id);
});

it('validates what the step sends', function (array $form, string $field): void {
    $this->actingAs($this->hr)->post(progressUrl($this), $form)->assertSessionHasErrors($field);

    expect(ProgressEntry::query()->count())->toBe(0);
})->with([
    'no status' => [['recorded_on' => '2026-11-19'], 'status'],
    'an unknown status' => [['status' => 'finished', 'recorded_on' => '2026-11-19'], 'status'],
    'a cancellation through progress' => [['status' => 'cancelled', 'recorded_on' => '2026-11-19'], 'status'],
    'no day' => [['status' => 'done'], 'recorded_on'],
    'a day that is not a date' => [['status' => 'done', 'recorded_on' => 'ayer'], 'recorded_on'],
    'a day in the future' => [['status' => 'done', 'recorded_on' => '2026-11-21'], 'recorded_on'],
    'a note too long' => [['status' => 'done', 'recorded_on' => '2026-11-19', 'note' => str_repeat('a', 2001)], 'note'],
]);

it('cancels an action with a reason', function (): void {
    $this->actingAs($this->hr)->post(cancelUrl($this), ['reason' => 'Ya no aplica.'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]));

    expect($this->action->fresh()?->status)->toBe(ActionStatus::Cancelled);
    $this->actingAs($this->hr)->post(cancelUrl($this, ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id])), ['reason' => ''])->assertSessionHasErrors('reason');
});

it('lets only owner and RH record or cancel', function (TenantRole $role, bool $allowed): void {
    $person = memberOf($this->tenant, $role);

    $this->actingAs($person)->post(progressUrl($this), ['status' => 'done', 'recorded_on' => '2026-11-19'])->assertStatus($allowed ? 302 : 403);
    $this->actingAs($person)->post(cancelUrl($this), ['reason' => 'Motivo'])->assertStatus($allowed ? 302 : 403);
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
    'committee' => [TenantRole::Committee, false],
]);

it('answers 404 for the action of another company or another Programa', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $foreign = ProgramAction::factory()->create(['tenant_id' => $other->id]);
    workIn($this->tenant);
    [$second] = closedEvaluationInAreas($this->tenant, ['Logística' => 6]);
    resolve(CreateProgram::class)->handle($second->id, null);

    $this->actingAs($this->hr)->post(progressUrl($this, $foreign), ['status' => 'done', 'recorded_on' => '2026-11-19'])->assertNotFound();
    $this->actingAs($this->hr)->post(cancelUrl($this, $foreign), ['reason' => 'Motivo'])->assertNotFound();
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.progress.store', [$this->tenant, $second->id, $this->action->id]), ['status' => 'done', 'recorded_on' => '2026-11-19'])->assertNotFound();

    expect($this->action->fresh()?->status)->toBe(ActionStatus::Planned);
});

it('has no way to edit or delete a step', function (): void {
    expect(collect(app('router')->getRoutes()->getRoutes())->filter(fn ($route): bool => str_contains($route->uri(), 'progress'))->map(fn ($route): array => $route->methods())->flatten()->unique()->values()->all())
        ->toBe(['POST']);
});

it('shows where the Programa stands: its state, its meter, each row and each action with its history', function (): void {
    $overdue = ProgramAction::factory()->inProgress()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id, 'due_on' => '2026-11-19']);
    ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id]);
    ProgramAction::factory()->cancelled()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id, 'due_on' => '2026-01-01']);
    ProgressEntry::factory()->create(['tenant_id' => $this->tenant->id, 'action_id' => $overdue->id, 'recorded_on' => '2026-11-10', 'note' => 'Arrancó.']);

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.state', ['value' => 'overdue', 'label' => 'Con acciones vencidas'])
            ->where('program.meter', ['done' => 1, 'total' => 3])
            ->where('program.items.0.state', ['value' => 'in_progress', 'label' => 'En curso'])
            ->where('program.items.1.state', ['value' => 'none', 'label' => 'Sin acciones'])
            ->where('actions.data', fn ($rows) => collect($rows)->firstWhere('id', $overdue->id)['overdue'] === true
                && collect($rows)->firstWhere('id', $this->action->id)['overdue'] === false
                && count(collect($rows)->firstWhere('id', $overdue->id)['history']) === 1
                && collect($rows)->firstWhere('id', $overdue->id)['history'][0]['onLabel'] === '10 nov 2026'
                && collect($rows)->firstWhere('id', $overdue->id)['history'][0]['status'] === ['value' => 'in_progress', 'label' => 'En curso']
                && collect($rows)->firstWhere('id', $overdue->id)['history'][0]['note'] === 'Arrancó.'));
});

it('says a Programa with nothing planned has no actions, and one with all done is done', function (): void {
    $url = route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]);
    $this->action->delete();

    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page->where('program.state.value', 'none')->where('program.meter', ['done' => 0, 'total' => 0]));

    ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id]);
    $others = ProgramItem::query()->where('program_id', $this->program?->id)->where('id', '!=', $this->item->id)->get();
    foreach ($others as $other) {
        ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $other->id]);
    }

    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page->where('program.state.value', 'done'));
});
