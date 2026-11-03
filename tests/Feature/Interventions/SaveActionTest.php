<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Actions\SaveAction;
use App\Modules\Interventions\Data\ActionData;
use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionStatus;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Enums\Professional;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(SaveAction::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    // Producción and Ventas are shown; Compras and Legal add up to 5, so they stay hidden without dragging another one.
    [$this->campaign, $this->areas] = closedEvaluationInAreas($this->tenant, ['Producción' => 7, 'Ventas' => 6, 'Compras' => 3, 'Legal' => 2]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->item = ProgramItem::query()->where('program_id', $this->program?->id)->where('scope', 'final')->sole();
});

/** @param  array<string, mixed>  $overrides */
function actionData(object $test, array $overrides = []): ActionData
{
    return new ActionData(...[
        'id' => null,
        'itemId' => $test->item->id,
        'kind' => ActionKind::PolicyReview,
        'level' => ActionLevel::Organizational,
        'title' => 'Revisar la política de prevención',
        'description' => 'Revisar y difundir la política.',
        'subjects' => ActionSubjects::Center,
        'areaIds' => [],
        'startsOn' => null,
        'dueOn' => '2026-12-15',
        'responsibleName' => 'Laura Méndez',
        'responsibleUserId' => null,
        'professional' => null,
        ...$overrides,
    ]);
}

/** Runs `$call` and checks it refused `$field`. */
function expectInvalid(Closure $call, string $field): void
{
    try {
        $call();
    } catch (ValidationException $invalid) {
        expect(array_keys($invalid->errors()))->toContain($field);

        return;
    }

    throw new RuntimeException("Expected {$field} to be rejected.");
}

it('saves an action for the whole center on a row of the Programa', function (): void {
    $action = resolve(SaveAction::class)->handle(actionData($this), $this->hr->id);

    expect($action->program_id)->toBe($this->program?->id)
        ->and($action->item_id)->toBe($this->item->id)
        ->and($action->level)->toBe(ActionLevel::Organizational)
        ->and($action->kind)->toBe(ActionKind::PolicyReview)
        ->and($action->subjects)->toBe(ActionSubjects::Center)
        ->and($action->status)->toBe(ActionStatus::Planned)
        ->and($action->responsible_name)->toBe('Laura Méndez')
        ->and($action->responsible_user_id)->toBeNull()
        ->and($action->professional)->toBeNull()
        ->and($action->due_on->toDateString())->toBe('2026-12-15')
        ->and($action->created_by)->toBe($this->hr->id)
        ->and(ActionArea::query()->count())->toBe(0);
});

it('saves an action for areas shown, and only those', function (): void {
    $action = resolve(SaveAction::class)->handle(actionData($this, ['subjects' => ActionSubjects::Areas, 'areaIds' => [$this->areas['Producción']->id, $this->areas['Ventas']->id]]), $this->hr->id);

    expect(ActionArea::query()->where('action_id', $action->id)->pluck('area_id')->sort()->values()->all())
        ->toBe(collect([$this->areas['Producción']->id, $this->areas['Ventas']->id])->sort()->values()->all());
});

it('rejects an area that is hidden, does not exist or belongs to another company, all with the same message', function (string $which): void {
    $other = Tenant::factory()->create();
    $foreign = App\Modules\Organization\Models\Area::factory()->create(['tenant_id' => $other->id]);
    $id = match ($which) {
        'hidden' => $this->areas['Compras']->id,
        'smallest hidden' => $this->areas['Legal']->id,
        'unknown' => '00000000-0000-4000-8000-000000000000',
        'foreign' => $foreign->id,
        'one shown, one hidden' => $this->areas['Compras']->id,
    };
    $ids = $which === 'one shown, one hidden' ? [$this->areas['Producción']->id, $id] : [$id];
    $messages = [];

    try {
        resolve(SaveAction::class)->handle(actionData($this, ['subjects' => ActionSubjects::Areas, 'areaIds' => $ids]), $this->hr->id);
    } catch (ValidationException $invalid) {
        $messages = $invalid->errors();
    }

    expect($messages)->toBe(['area_ids' => ['Elige áreas de la lista.']])
        ->and(ProgramAction::query()->count())->toBe(0)
        ->and(ActionArea::query()->count())->toBe(0);
})->with(['hidden', 'smallest hidden', 'unknown', 'foreign', 'one shown, one hidden']);

it('asks for at least one area when the action is for areas', function (): void {
    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['subjects' => ActionSubjects::Areas, 'areaIds' => []]), $this->hr->id), 'area_ids');
});

it('ignores the areas of an action for the whole center', function (): void {
    $action = resolve(SaveAction::class)->handle(actionData($this, ['subjects' => ActionSubjects::Center, 'areaIds' => [$this->areas['Compras']->id]]), $this->hr->id);

    expect(ActionArea::query()->where('action_id', $action->id)->count())->toBe(0);
});

it('asks for the professional on the third level, which is clinical care only', function (): void {
    $clinical = ['kind' => ActionKind::ClinicalCare, 'level' => ActionLevel::Individual];

    $action = resolve(SaveAction::class)->handle(actionData($this, [...$clinical, 'professional' => Professional::Psychologist, 'title' => 'Atención psicológica a solicitud']), $this->hr->id);

    expect($action->level)->toBe(ActionLevel::Individual)
        ->and($action->professional)->toBe(Professional::Psychologist);
    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, [...$clinical, 'professional' => null]), $this->hr->id), 'professional');
    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['kind' => ActionKind::ClinicalCare, 'level' => ActionLevel::Organizational]), $this->hr->id), 'level');
    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['kind' => ActionKind::Training, 'level' => ActionLevel::Individual, 'professional' => Professional::Physician]), $this->hr->id), 'level');
});

it('forgets the professional of an action that is not of the third level', function (): void {
    $action = resolve(SaveAction::class)->handle(actionData($this, ['professional' => Professional::Physician]), $this->hr->id);

    expect($action->professional)->toBeNull();
});

it('takes any of the first two levels for a kind of its own', function (ActionLevel $level): void {
    $action = resolve(SaveAction::class)->handle(actionData($this, ['kind' => ActionKind::Other, 'level' => $level]), $this->hr->id);

    expect($action->level)->toBe($level);
})->with([ActionLevel::Organizational, ActionLevel::Group]);

it('rejects a due date before the start and accepts the same day', function (): void {
    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['startsOn' => '2026-12-10', 'dueOn' => '2026-12-09']), $this->hr->id), 'due_on');

    $action = resolve(SaveAction::class)->handle(actionData($this, ['startsOn' => '2026-12-10', 'dueOn' => '2026-12-10']), $this->hr->id);

    expect($action->starts_on?->toDateString())->toBe('2026-12-10');
});

it('names a member of the company as responsible, by their own name', function (): void {
    $member = memberOf($this->tenant, TenantRole::Viewer);
    $member->update(['name' => 'Vera Consulta']);

    $action = resolve(SaveAction::class)->handle(actionData($this, ['responsibleUserId' => $member->id, 'responsibleName' => 'Otro nombre']), $this->hr->id);

    expect($action->responsible_user_id)->toBe($member->id)
        ->and($action->responsible_name)->toBe('Vera Consulta');
});

it('rejects a responsible who belongs to another company', function (): void {
    $stranger = memberOf(Tenant::factory()->create(), TenantRole::Hr);

    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['responsibleUserId' => $stranger->id]), $this->hr->id), 'responsible_user_id');
    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['responsibleUserId' => 999999]), $this->hr->id), 'responsible_user_id');
});

it('takes the responsible of the Programa when none is given, and asks for one when there is none', function (): void {
    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['responsibleName' => null]), $this->hr->id), 'responsible_name');
    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['responsibleName' => '  ']), $this->hr->id), 'responsible_name');

    $member = memberOf($this->tenant, TenantRole::Hr);
    $this->program?->update(['responsible_user_id' => $member->id, 'responsible_name' => $member->name]);

    $action = resolve(SaveAction::class)->handle(actionData($this, ['responsibleName' => null]), $this->hr->id);

    expect($action->responsible_user_id)->toBe($member->id)
        ->and($action->responsible_name)->toBe($member->name);
});

it('edits an action, replaces its areas and keeps its row and who created it', function (): void {
    $first = resolve(SaveAction::class)->handle(actionData($this, ['subjects' => ActionSubjects::Areas, 'areaIds' => [$this->areas['Producción']->id]]), $this->hr->id);
    $otherUser = User::factory()->create();

    $edited = resolve(SaveAction::class)->handle(actionData($this, ['id' => $first->id, 'itemId' => null, 'title' => 'Otro título', 'subjects' => ActionSubjects::Areas, 'areaIds' => [$this->areas['Ventas']->id]]), $otherUser->id);

    expect($edited->id)->toBe($first->id)
        ->and($edited->title)->toBe('Otro título')
        ->and($edited->created_by)->toBe($this->hr->id)
        ->and($edited->item_id)->toBe($this->item->id)
        ->and(ActionArea::query()->where('action_id', $first->id)->pluck('area_id')->all())->toBe([$this->areas['Ventas']->id])
        ->and(ProgramAction::query()->count())->toBe(1);

    resolve(SaveAction::class)->handle(actionData($this, ['id' => $first->id, 'itemId' => null, 'subjects' => ActionSubjects::Center]), $this->hr->id);
    expect(ActionArea::query()->count())->toBe(0);
});

it('does not edit a cancelled action', function (): void {
    $action = ProgramAction::factory()->cancelled()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id]);

    expectInvalid(fn () => resolve(SaveAction::class)->handle(actionData($this, ['id' => $action->id, 'itemId' => null, 'title' => 'Nuevo']), $this->hr->id), 'status');

    expect($action->fresh()?->title)->toBe('Revisar la política de prevención');
});

it('does not find the row or the action of another company', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $foreign = ProgramAction::factory()->create(['tenant_id' => $other->id]);
    workIn($this->tenant);

    expect(fn () => resolve(SaveAction::class)->handle(actionData($this, ['itemId' => $foreign->item_id]), $this->hr->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => resolve(SaveAction::class)->handle(actionData($this, ['id' => $foreign->id, 'itemId' => null]), $this->hr->id))->toThrow(ModelNotFoundException::class)
        ->and(fn () => resolve(SaveAction::class)->handle(actionData($this, ['itemId' => null]), $this->hr->id))->toThrow(ModelNotFoundException::class);
});

it('logs the save with ids and enumerations, never what HR wrote', function (): void {
    Log::spy();

    $action = resolve(SaveAction::class)->handle(actionData($this, ['title' => 'Plan para Juan Pérez', 'description' => 'Hablar con Juan Pérez.']), $this->hr->id);

    Log::shouldHaveReceived('info')->with('intervention.action_saved', [
        'action_id' => $action->id,
        'program_id' => $this->program?->id,
        'item_id' => $this->item->id,
        'level' => 1,
        'kind' => 'policy_review',
        'subjects' => 'center',
        'created' => true,
    ])->once();
});
