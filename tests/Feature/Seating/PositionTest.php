<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Actions\SavePosition;
use App\Modules\Seating\Enums\BipedestationType;
use App\Modules\Seating\Http\Controllers\PositionController;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SavePosition::class, PositionController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function positionForm(array $overrides = []): array
{
    return [
        'name' => 'Cajera',
        'tasks' => 'Cobra y empaca de pie en la línea de cajas.',
        'bipedestation_type' => 'prolonged',
        'continuous_minutes' => 240,
        'can_work_seated' => true,
        'has_space_for_seat' => true,
        'needs_to_be_nearby' => false,
        'max_people_at_once' => 3,
        'area_conditions' => ['noise' => 'Moderado', 'lighting' => 'Artificial', 'ventilation' => 'Natural', 'temperature' => 'Templada', 'vibration' => 'Ninguna', 'chemicals' => 'Ninguno'],
        'tools' => 'Lector de código de barras y báscula.',
        'physical_load' => 'Levantar bolsas de hasta 5 kg.',
        'attention_level' => 'Visual y motora',
        'default_standing_time' => 3,
        'default_posture_change' => 2,
        'default_surface' => 3,
        'default_movement_space' => 2,
        'default_pauses' => 2,
        ...$overrides,
    ];
}

function savePosition(array $overrides = [], ?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.seating.positions.store', [test()->tenant, ($center ?? test()->center)->id]), positionForm($overrides));
}

it('derives the placement from the three Figure 1 answers and never trusts a posted one', function (): void {
    $this->actingAs($this->hr);

    savePosition(['can_work_seated' => true, 'has_space_for_seat' => false, 'needs_to_be_nearby' => true, 'placement' => 'at_workstation', 'figure_one_applies' => false])->assertSessionHasNoErrors();

    $position = StandingPosition::query()->sole();
    expect($position->placement?->value)->toBe('nearby')
        ->and($position->figure_one_applies)->toBeTrue()
        ->and($position->work_center_id)->toBe($this->center->id)
        ->and($position->bipedestation_type)->toBe(BipedestationType::Prolonged)
        ->and($position->area_conditions['noise'])->toBe('Moderado');
});

it('answers questions 3 to 5 for a position under three hours and marks figure_one_applies false', function (int $minutes, bool $prolonged): void {
    $this->actingAs($this->hr);

    savePosition(['continuous_minutes' => $minutes, 'can_work_seated' => false, 'needs_to_be_nearby' => false])->assertSessionHasNoErrors();

    $position = StandingPosition::query()->sole();
    expect($position->figure_one_applies)->toBe($prolonged)
        ->and($position->placement?->value)->toBe('within_center');
})->with([
    'exactly three hours is not more than three hours' => [180, false],
    'one minute over' => [181, true],
    'a short stand' => [30, false],
]);

it('requires a reason when an industrial position says the nature does not allow seating', function (): void {
    SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'sector' => 'industrial']);
    $this->actingAs($this->hr);

    savePosition(['nature_allows' => false, 'nature_reason' => '  '])->assertSessionHasErrors('nature_reason');
    expect(StandingPosition::query()->count())->toBe(0);

    savePosition(['nature_allows' => false, 'nature_reason' => 'Opera una prensa que exige estar de pie.'])->assertSessionHasNoErrors();

    $position = StandingPosition::query()->sole();
    expect($position->nature_allows)->toBeFalse()
        ->and($position->nature_reason)->toBe('Opera una prensa que exige estar de pie.')
        ->and($position->placement)->toBeNull();
});

it('ignores the nature of the work in a center that is not industrial', function (?string $sector): void {
    if ($sector !== null) {
        SeatingProfile::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'sector' => $sector]);
    }
    $this->actingAs($this->hr);

    savePosition(['nature_allows' => false, 'nature_reason' => 'No importa'])->assertSessionHasNoErrors();

    $position = StandingPosition::query()->sole();
    expect($position->nature_allows)->toBeNull()->and($position->nature_reason)->toBeNull()->and($position->placement)->not->toBeNull();
})->with(['services' => 'services', 'commerce' => 'commerce', 'undeclared' => null]);

it('updates a position and recomputes its placement', function (): void {
    $position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->actingAs($this->hr);

    $this->put(route('tenant.seating.positions.update', [$this->tenant, $position->id]), positionForm(['name' => 'Cajera senior', 'can_work_seated' => false, 'needs_to_be_nearby' => true]))
        ->assertRedirect(route('tenant.seating.positions.show', [$this->tenant, $position->id]));

    $fresh = StandingPosition::query()->sole();
    expect($fresh->name)->toBe('Cajera senior')->and($fresh->placement?->value)->toBe('nearby');
});

it('rejects what a position cannot be', function (array $bad, string $field): void {
    $this->actingAs($this->hr);

    savePosition($bad)->assertSessionHasErrors($field);

    expect(StandingPosition::query()->count())->toBe(0);
})->with([
    'no name' => [['name' => ''], 'name'],
    'no tasks' => [['tasks' => ''], 'tasks'],
    'unknown type' => [['bipedestation_type' => 'floating'], 'bipedestation_type'],
    'a day and a minute' => [['continuous_minutes' => 1441], 'continuous_minutes'],
    'negative minutes' => [['continuous_minutes' => -1], 'continuous_minutes'],
    'nobody at once' => [['max_people_at_once' => 0], 'max_people_at_once'],
    'a condition of 0' => [['default_pauses' => 0], 'default_pauses'],
    'a condition of 4' => [['default_surface' => 4], 'default_surface'],
    'a missing answer of Figure 1' => [['has_space_for_seat' => null], 'has_space_for_seat'],
    'a long agent description' => [['area_conditions' => ['noise' => str_repeat('a', 201)]], 'area_conditions.noise'],
]);

it('lets only manage-seating write positions', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role));

    savePosition()->assertForbidden();
    $this->get(route('tenant.seating.positions.create', [$this->tenant, $this->center->id]))->assertForbidden();

    expect(StandingPosition::query()->count())->toBe(0);
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('shows a position to who may see its center and nothing of people to the viewer', function (TenantRole $role, bool $people, bool $manage): void {
    $position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $user = memberOf($this->tenant, $role);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $user->id]);

    $this->actingAs($user)->get(route('tenant.seating.positions.show', [$this->tenant, $position->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('seating/position')
            ->where('position.name', 'Cajera')
            ->where('position.placement', 'at_workstation')
            ->where('canManage', $manage)
            ->where('people', fn ($list) => ($list !== null) === $people));
})->with([
    'owner' => [TenantRole::Owner, true, true],
    'hr' => [TenantRole::Hr, true, true],
    'committee' => [TenantRole::Committee, true, false],
    'viewer' => [TenantRole::Viewer, false, false],
]);

it('answers 404 for a position of a center the committee does not sit on and for a made-up one', function (): void {
    $position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Committee))->get(route('tenant.seating.positions.show', [$this->tenant, $position->id]))->assertNotFound();
    $this->actingAs($this->hr)->get(route('tenant.seating.positions.show', [$this->tenant, (string) Illuminate\Support\Str::uuid()]))->assertNotFound();
});

it('opens the form for a new position', function (): void {
    $this->actingAs($this->hr)->get(route('tenant.seating.positions.create', [$this->tenant, $this->center->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('seating/position')->where('position', null)->where('canManage', true)->has('types', 4)->has('conditions', 5)->where('industrial', false));
});

it('records the position in the audit trail and the log with ids only', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    savePosition(['name' => 'Nombre sensible', 'tasks' => 'Texto'])->assertSessionHasNoErrors();

    $position = StandingPosition::query()->sole();
    $entry = AuditEntry::query()->where('event', 'seating.position_saved')->sole();
    expect($entry->metadata)->toEqual(['work_center_id' => $this->center->id, 'position_id' => $position->id, 'placement' => 'at_workstation']);
    Log::shouldHaveReceived('info')->with('seating.position_saved', ['work_center_id' => $this->center->id, 'position_id' => $position->id, 'placement' => 'at_workstation'])->once();
});

it('refuses in the database a placement that does not follow the answers, and a reason-less nature refusal', function (): void {
    $position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    $attempts = [
        'standing_positions_placement_follows_answers' => fn () => DB::table('standing_positions')->update(['placement' => 'within_center']),
        'standing_positions_figure_one_follows_minutes' => fn () => DB::table('standing_positions')->update(['figure_one_applies' => false]),
        'standing_positions_nature_needs_reason' => fn () => DB::table('standing_positions')->update(['nature_allows' => false, 'nature_reason' => null, 'placement' => null]),
        'standing_positions_default_surface_range' => fn () => DB::table('standing_positions')->update(['default_surface' => 4]),
        'standing_positions_people_positive' => fn () => DB::table('standing_positions')->update(['max_people_at_once' => 0]),
    ];

    foreach ($attempts as $constraint => $attempt) {
        expect(fn () => DB::transaction($attempt))->toThrow(QueryException::class, $constraint);
    }
    expect($position->fresh()?->placement?->value)->toBe('at_workstation');
});
