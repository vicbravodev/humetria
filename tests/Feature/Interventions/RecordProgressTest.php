<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CancelAction;
use App\Modules\Interventions\Actions\RecordProgress;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgressEntry;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(RecordProgress::class, CancelAction::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->user = memberOf($this->tenant, App\Modules\Tenancy\Enums\TenantRole::Hr);
});

function plannedAction(object $test, string $state = 'planned'): ProgramAction
{
    $factory = ProgramAction::factory();
    $factory = match ($state) {
        'in_progress' => $factory->inProgress(),
        'done' => $factory->done(),
        'cancelled' => $factory->cancelled(),
        default => $factory,
    };

    return $factory->create(['tenant_id' => $test->tenant->id]);
}

it('moves an action forward, leaving a row in the log each time', function (): void {
    $action = plannedAction($this);

    resolve(RecordProgress::class)->handle($action->id, ActionStatus::InProgress, '2026-11-18', 'Se aprobó el presupuesto.', $this->user->id);
    resolve(RecordProgress::class)->handle($action->id, ActionStatus::Done, '2026-11-19', null, $this->user->id);

    $fresh = $action->fresh();
    $entries = ProgressEntry::query()->where('action_id', $action->id)->orderBy('recorded_on')->get();

    expect($fresh?->status)->toBe(ActionStatus::Done)
        ->and($fresh?->completed_on?->toDateString())->toBe('2026-11-19')
        ->and($entries->map(fn (ProgressEntry $entry): array => [$entry->status, $entry->recorded_on->toDateString(), $entry->note, $entry->recorded_by])->all())
        ->toBe([[ActionStatus::InProgress, '2026-11-18', 'Se aprobó el presupuesto.', $this->user->id], [ActionStatus::Done, '2026-11-19', null, $this->user->id]]);
});

it('goes straight from planned to done', function (): void {
    $action = plannedAction($this);

    resolve(RecordProgress::class)->handle($action->id, ActionStatus::Done, '2026-11-20', null, $this->user->id);

    expect($action->fresh()?->status)->toBe(ActionStatus::Done)
        ->and($action->fresh()?->completed_on?->toDateString())->toBe('2026-11-20');
});

it('reopens a done action, which loses its completion day and keeps the history', function (): void {
    $action = plannedAction($this, 'done');

    resolve(RecordProgress::class)->handle($action->id, ActionStatus::InProgress, '2026-11-20', 'Falta una parte.', $this->user->id);

    expect($action->fresh()?->status)->toBe(ActionStatus::InProgress)
        ->and($action->fresh()?->completed_on)->toBeNull()
        ->and(ProgressEntry::query()->where('action_id', $action->id)->count())->toBe(1);
});

it('lets a running action log a note without changing state', function (): void {
    $action = plannedAction($this, 'in_progress');

    resolve(RecordProgress::class)->handle($action->id, ActionStatus::InProgress, '2026-11-20', 'Va al 50 %.', $this->user->id);

    expect($action->fresh()?->status)->toBe(ActionStatus::InProgress)
        ->and(ProgressEntry::query()->count())->toBe(1);
});

it('refuses the moves that are not forward, the same state or a cancellation', function (string $from, ActionStatus $to): void {
    $action = plannedAction($this, $from);

    expect(fn () => resolve(RecordProgress::class)->handle($action->id, $to, '2026-11-20', null, $this->user->id))->toThrow(ValidationException::class)
        ->and(ProgressEntry::query()->count())->toBe(0)
        ->and($action->fresh()?->status->value)->toBe($from);
})->with([
    'planned to planned' => ['planned', ActionStatus::Planned],
    'running back to planned' => ['in_progress', ActionStatus::Planned],
    'done to done' => ['done', ActionStatus::Done],
    'done back to planned' => ['done', ActionStatus::Planned],
    'planned to cancelled, which has its own way' => ['planned', ActionStatus::Cancelled],
]);

it('refuses to move a cancelled action', function (): void {
    $action = plannedAction($this, 'cancelled');

    expect(fn () => resolve(RecordProgress::class)->handle($action->id, ActionStatus::InProgress, '2026-11-20', null, $this->user->id))->toThrow(ValidationException::class);
});

it('does not take a day in the future, and takes today', function (): void {
    $action = plannedAction($this);

    expect(fn () => resolve(RecordProgress::class)->handle($action->id, ActionStatus::Done, '2026-11-21', null, $this->user->id))->toThrow(ValidationException::class)
        ->and($action->fresh()?->status)->toBe(ActionStatus::Planned);

    resolve(RecordProgress::class)->handle($action->id, ActionStatus::Done, '2026-11-20', null, $this->user->id);
    expect($action->fresh()?->status)->toBe(ActionStatus::Done);
});

it('takes the day in the display time zone, not in UTC', function (): void {
    // 02:00 UTC of the 21st is still the evening of the 20th in Mexico City.
    Carbon::setTestNow('2026-11-21 02:00:00');
    $action = plannedAction($this);

    resolve(RecordProgress::class)->handle($action->id, ActionStatus::Done, '2026-11-20', null, $this->user->id);

    expect($action->fresh()?->status)->toBe(ActionStatus::Done)
        ->and(fn () => resolve(RecordProgress::class)->handle(plannedAction($this)->id, ActionStatus::Done, '2026-11-21', null, $this->user->id))->toThrow(ValidationException::class);
});

it('does not find the action of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = ProgramAction::factory()->create(['tenant_id' => $other->id]);

    expect(fn () => resolve(RecordProgress::class)->handle($foreign->id, ActionStatus::Done, '2026-11-20', null, $this->user->id))->toThrow(ModelNotFoundException::class);
});

it('writes the entry and the state together or neither', function (): void {
    $action = plannedAction($this);
    ProgramAction::updating(fn () => throw new RuntimeException('boom'));

    expect(fn () => resolve(RecordProgress::class)->handle($action->id, ActionStatus::Done, '2026-11-20', null, $this->user->id))->toThrow(RuntimeException::class)
        ->and(ProgressEntry::query()->count())->toBe(0);
});

it('logs the step without the note', function (): void {
    Log::spy();
    $action = plannedAction($this);

    resolve(RecordProgress::class)->handle($action->id, ActionStatus::InProgress, '2026-11-20', 'Hablé con Juan Pérez.', $this->user->id);

    Log::shouldHaveReceived('info')->with('intervention.progress_recorded', ['action_id' => $action->id, 'program_id' => $action->program_id, 'from' => 'planned', 'to' => 'in_progress', 'with_evidence' => false])->once();
});

it('cancels an action with a reason, which stays in the log', function (): void {
    Log::spy();
    $action = plannedAction($this, 'in_progress');

    resolve(CancelAction::class)->handle($action->id, 'Ya no aplica: cambió el proceso.', $this->user->id);

    $entry = ProgressEntry::query()->sole();
    expect($action->fresh()?->status)->toBe(ActionStatus::Cancelled)
        ->and($action->fresh()?->cancelled_reason)->toBe('Ya no aplica: cambió el proceso.')
        ->and($entry->status)->toBe(ActionStatus::Cancelled)
        ->and($entry->note)->toBe('Ya no aplica: cambió el proceso.')
        ->and($entry->recorded_on->toDateString())->toBe('2026-11-20');
    Log::shouldHaveReceived('info')->with('intervention.action_cancelled', ['action_id' => $action->id, 'program_id' => $action->program_id])->once();
    expect(App\Models\AuditEntry::query()->where('event', 'interventions.action_cancelled')->sole()->metadata)->toBe(['action_id' => $action->id, 'program_id' => $action->program_id]);
});

it('does not cancel without a reason, a done action or one already cancelled', function (string $state, string $reason): void {
    $action = plannedAction($this, $state);

    expect(fn () => resolve(CancelAction::class)->handle($action->id, $reason, $this->user->id))->toThrow(ValidationException::class)
        ->and($action->fresh()?->status->value)->toBe($state)
        ->and(ProgressEntry::query()->count())->toBe(0);
})->with([
    'no reason' => ['planned', ''],
    'a blank reason' => ['in_progress', '   '],
    'a done action' => ['done', 'Motivo'],
    'a cancelled action' => ['cancelled', 'Motivo'],
]);
