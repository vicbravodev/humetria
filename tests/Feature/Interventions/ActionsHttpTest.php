<?php

declare(strict_types=1);

use App\Modules\Interventions\Actions\CreateProgram;
use App\Modules\Interventions\Http\Controllers\ActionController;
use App\Modules\Interventions\Models\ActionArea;
use App\Modules\Interventions\Models\ProgramAction;
use App\Modules\Interventions\Models\ProgramItem;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ActionController::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    [$this->campaign, $this->areas] = closedEvaluationInAreas($this->tenant, ['Producción' => 7, 'Ventas' => 6, 'Compras' => 3, 'Legal' => 2]);
    workIn($this->tenant);
    $this->program = resolve(CreateProgram::class)->handle($this->campaign->id, null);
    $this->item = ProgramItem::query()->where('program_id', $this->program?->id)->where('scope', 'final')->sole();
});

/** What the action form sends. */
function actionForm(array $overrides = []): array
{
    return [
        'kind' => 'policy_review',
        'level' => 1,
        'title' => 'Revisar la política de prevención',
        'description' => 'Revisar y difundir la política.',
        'subjects' => 'center',
        'area_ids' => [],
        'starts_on' => null,
        'due_on' => '2026-12-15',
        'responsible_name' => 'Laura Méndez',
        'responsible_user_id' => null,
        'professional' => null,
        ...$overrides,
    ];
}

function storeActionUrl(object $test, ?ProgramItem $item = null): string
{
    return route('tenant.campaigns.program.actions.store', [$test->tenant, $test->campaign->id, ($item ?? $test->item)->id]);
}

it('adds an action to a row and goes back to the Programa', function (): void {
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]));

    $action = ProgramAction::query()->sole();
    expect($action->item_id)->toBe($this->item->id)
        ->and($action->title)->toBe('Revisar la política de prevención')
        ->and($action->created_by)->toBe($this->hr->id);
});

it('adds an action for the areas shown and refuses the hidden ones', function (): void {
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm(['subjects' => 'areas', 'area_ids' => [$this->areas['Producción']->id]]))->assertSessionHasNoErrors();
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm(['subjects' => 'areas', 'area_ids' => [$this->areas['Compras']->id]]))->assertSessionHasErrors(['area_ids' => 'Elige áreas de la lista.']);

    expect(ActionArea::query()->pluck('area_id')->all())->toBe([$this->areas['Producción']->id]);
});

it('validates what the form sends', function (array $overrides, string $field): void {
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm($overrides))->assertSessionHasErrors($field);

    expect(ProgramAction::query()->count())->toBe(0);
})->with([
    'no title' => [['title' => ''], 'title'],
    'a title too long' => [['title' => str_repeat('a', 161)], 'title'],
    'no description' => [['description' => ''], 'description'],
    'no due date' => [['due_on' => null], 'due_on'],
    'a due date that is not a date' => [['due_on' => '15/12/2026'], 'due_on'],
    'an unknown kind' => [['kind' => 'magic'], 'kind'],
    'an unknown level' => [['level' => 4], 'level'],
    'unknown subjects' => [['subjects' => 'people'], 'subjects'],
    'areas that are not a list' => [['subjects' => 'areas', 'area_ids' => 'x'], 'area_ids'],
    'an area that is not an id' => [['subjects' => 'areas', 'area_ids' => ['x']], 'area_ids.0'],
    'an unknown professional' => [['level' => 3, 'kind' => 'clinical_care', 'professional' => 'nurse'], 'professional'],
    'a third level without a professional' => [['level' => 3, 'kind' => 'clinical_care'], 'professional'],
    'a due date before the start' => [['starts_on' => '2026-12-16'], 'due_on'],
]);

it('calls the due date «fecha límite» in the error, as the screen does', function (): void {
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm(['due_on' => null]))
        ->assertSessionHasErrors(['due_on' => 'El campo fecha límite es obligatorio.']);
});

it('edits an action and shows the change', function (): void {
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm())->assertSessionHasNoErrors();
    $action = ProgramAction::query()->sole();

    $this->actingAs($this->hr)->put(route('tenant.campaigns.program.actions.update', [$this->tenant, $this->campaign->id, $action->id]), actionForm(['title' => 'Otro título']))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]));

    expect($action->fresh()?->title)->toBe('Otro título');
});

it('lets only owner and RH write', function (TenantRole $role, bool $allowed): void {
    $person = memberOf($this->tenant, $role);

    $this->actingAs($person)->post(storeActionUrl($this), actionForm())->assertStatus($allowed ? 302 : 403);

    expect(ProgramAction::query()->count())->toBe($allowed ? 1 : 0);
})->with([
    'owner' => [TenantRole::Owner, true],
    'hr' => [TenantRole::Hr, true],
    'viewer' => [TenantRole::Viewer, false],
    'committee' => [TenantRole::Committee, false],
]);

it('answers 404 for a row or an action that is not of this Programa or this company', function (): void {
    $other = Tenant::factory()->create();
    workIn($other);
    $foreign = ProgramAction::factory()->create(['tenant_id' => $other->id]);
    workIn($this->tenant);
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm())->assertSessionHasNoErrors();
    $mine = ProgramAction::query()->sole();
    [$second] = closedEvaluationInAreas($this->tenant, ['Logística' => 6]);
    $secondProgram = resolve(CreateProgram::class)->handle($second->id, null);
    $secondItem = ProgramItem::query()->where('program_id', $secondProgram?->id)->firstOrFail();

    // A row of another company, or of another Programa of the same company.
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.actions.store', [$this->tenant, $this->campaign->id, $foreign->item_id]), actionForm())->assertNotFound();
    $this->actingAs($this->hr)->post(storeActionUrl($this, $secondItem), actionForm())->assertNotFound();
    // An action of another company, or of another Programa.
    $this->actingAs($this->hr)->put(route('tenant.campaigns.program.actions.update', [$this->tenant, $this->campaign->id, $foreign->id]), actionForm())->assertNotFound();
    $this->actingAs($this->hr)->put(route('tenant.campaigns.program.actions.update', [$this->tenant, $second->id, $mine->id]), actionForm())->assertNotFound();
    // An evaluation with no Programa.
    $this->actingAs($this->hr)->post(route('tenant.campaigns.program.actions.store', [$this->tenant, '9f6c1c55-7d3e-4f43-9c1e-6f3c2a4a1b2c', $this->item->id]), actionForm())->assertNotFound();

    workIn($other);
    expect(ProgramAction::query()->sole()->title)->toBe('Revisar la política de prevención');
});

it('sets who answers for the Programa', function (): void {
    $member = memberOf($this->tenant, TenantRole::Hr);

    $this->actingAs($this->hr)->put(route('tenant.campaigns.program.responsible', [$this->tenant, $this->campaign->id]), ['responsible_user_id' => $member->id, 'responsible_name' => null])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]));
    expect($this->program?->fresh()?->responsible_user_id)->toBe($member->id);

    $this->actingAs($this->hr)->put(route('tenant.campaigns.program.responsible', [$this->tenant, $this->campaign->id]), ['responsible_user_id' => null, 'responsible_name' => ''])->assertSessionHasErrors('responsible_name');
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->put(route('tenant.campaigns.program.responsible', [$this->tenant, $this->campaign->id]), ['responsible_name' => 'Alguien'])->assertForbidden();
});

it('shows the actions, the choices of the form and who can answer, to those who write', function (): void {
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm(['subjects' => 'areas', 'area_ids' => [$this->areas['Producción']->id], 'starts_on' => '2026-12-01']))->assertSessionHasNoErrors();
    $action = ProgramAction::query()->sole();

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('actions.total', 1)
            ->where('actions.data.0', fn ($row) => $row['id'] === $action->id
                && $row['itemId'] === $this->item->id
                && $row['itemLabel'] === 'Calificación final'
                && $row['level'] === ['value' => 1, 'label' => 'Primer nivel: organizacional']
                && $row['kind'] === ['value' => 'policy_review', 'label' => 'Revisión de la política de prevención']
                && $row['title'] === 'Revisar la política de prevención'
                && $row['subjects'] === ['value' => 'areas', 'label' => 'Áreas']
                && $row['areas'] === [['id' => $this->areas['Producción']->id, 'name' => 'Producción']]
                && $row['startsOn'] === '2026-12-01' && $row['startsOnLabel'] === '1 dic 2026'
                && $row['dueOn'] === '2026-12-15' && $row['dueOnLabel'] === '15 dic 2026'
                && $row['responsible'] === ['name' => 'Laura Méndez', 'userId' => null]
                && $row['hiddenAreas'] === 0
                && $row['professional'] === null
                && $row['status'] === ['value' => 'planned', 'label' => 'Planeada'])
            ->where('filters', ['item' => null, 'status' => null])
            ->has('catalog.kinds', 10)
            ->where('catalog.kinds.0', ['value' => 'policy_review', 'label' => 'Revisión de la política de prevención', 'level' => 1])
            ->where('catalog.kinds.9', ['value' => 'other', 'label' => 'Otra acción', 'level' => null])
            ->where('catalog.levels', [['value' => 1, 'label' => 'Primer nivel: organizacional'], ['value' => 2, 'label' => 'Segundo nivel: grupal'], ['value' => 3, 'label' => 'Tercer nivel: individual']])
            ->where('catalog.professionals', [['value' => 'physician', 'label' => 'Médico'], ['value' => 'psychologist', 'label' => 'Psicólogo'], ['value' => 'psychiatrist', 'label' => 'Psiquiatra']])
            // Only the areas Results shows; for each row, how many questionnaires of the area are in Medio or more.
            ->where('areaOptions', fn ($options) => collect($options)->pluck('name')->sort()->values()->all() === ['Producción', 'Ventas']
                && collect($options)->firstWhere('name', 'Producción')['atRisk'][$this->item->id] === 7)
            ->where('members', [['id' => $this->hr->id, 'name' => $this->hr->name]]));
});

it('filters the actions by row and by status, and pages them on the server', function (): void {
    $other = ProgramItem::query()->where('program_id', $this->program?->id)->where('scope', '!=', 'final')->firstOrFail();
    ProgramAction::factory()->count(3)->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id]);
    ProgramAction::factory()->done()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $other->id]);
    $url = route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]);

    $this->actingAs($this->hr)->get($url)->assertInertia(fn (Assert $page) => $page->where('actions.total', 4)->where('actions.per_page', 10));
    $this->actingAs($this->hr)->get("{$url}?item={$other->id}")->assertInertia(fn (Assert $page) => $page->where('actions.total', 1)->where('filters.item', $other->id));
    $this->actingAs($this->hr)->get("{$url}?status=done")->assertInertia(fn (Assert $page) => $page->where('actions.total', 1)->where('filters.status', 'done'));
    $this->actingAs($this->hr)->get("{$url}?status=planned")->assertInertia(fn (Assert $page) => $page->where('actions.total', 3));
    // A filter that is not an id or a status is not applied.
    $this->actingAs($this->hr)->get("{$url}?item=x&status=zzz")->assertOk()->assertInertia(fn (Assert $page) => $page->where('actions.total', 4)->where('filters', ['item' => null, 'status' => null]));
});

it('gives the viewer the actions without the tools to write them', function (): void {
    ProgramAction::factory()->create(['tenant_id' => $this->tenant->id, 'program_id' => $this->program?->id, 'item_id' => $this->item->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => $page->where('actions.total', 1)->where('catalog', null)->where('areaOptions', null)->where('members', null));
});

it('never carries an individual response in its props', function (): void {
    $this->actingAs($this->hr)->post(storeActionUrl($this), actionForm(['subjects' => 'areas', 'area_ids' => [$this->areas['Producción']->id]]));

    $this->actingAs($this->hr)->get(route('tenant.campaigns.program', [$this->tenant, $this->campaign->id]))
        ->assertInertia(fn (Assert $page) => expectNoIndividualResponses($page->toArray()['props']));
});
