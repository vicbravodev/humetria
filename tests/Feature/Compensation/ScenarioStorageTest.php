<?php

declare(strict_types=1);

use App\Modules\Compensation\Models\Scenario;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The database holds the rules of a scenario by itself (CLAUDE.md, «Base de datos»): what the editor and the action check is
 * checked again here, so nothing that skips them can store a scenario the calculation cannot run.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/**
 * Writes a scenario past the model, inside a savepoint so the failed statement does not poison the test's transaction.
 *
 * @param  array<string, mixed>  $override
 */
function refusedScenario(Tenant $tenant, string $constraint, array $override = []): void
{
    expect(fn () => DB::transaction(fn () => DB::table('scenarios')->insert([
        'id' => (string) Str::uuid(), 'tenant_id' => $tenant->id, 'name' => 'Revisión 2027', 'effective_on' => '2027-01-01',
        'retroactive_months' => 0, 'horizon_years' => 3, 'group_changes' => '{}', 'term_changes' => '[]', 'assumptions' => '{}',
        'created_at' => now(), 'updated_at' => now(), ...$override,
    ])))->toThrow(QueryException::class, $constraint);
}

it('stores a scenario with a random UUID v4 and its changes as data', function (): void {
    $scenario = Scenario::factory()->create([
        'tenant_id' => $this->tenant->id,
        'group_changes' => ['unionized' => 500, 'trusted' => null],
        'term_changes' => [['benefit_id' => (string) Str::uuid(), 'value' => 2000, 'tenure_table' => null]],
    ]);

    $saved = Scenario::query()->findOrFail($scenario->id);

    expect(Str::isUuid($saved->id, 4))->toBeTrue()
        ->and($saved->group_changes)->toEqual(['unionized' => 500, 'trusted' => null])
        ->and($saved->term_changes)->toHaveCount(1)
        ->and($saved->effective_on->toDateString())->toBe('2027-01-01')
        ->and($saved->archived_at)->toBeNull();
});

it('refuses a horizon of 2 or 6 years', function (int $years): void {
    refusedScenario($this->tenant, 'scenarios_horizon_range', ['horizon_years' => $years]);
})->with([2, 6]);

it('refuses negative retroactivity and retroactivity longer than the horizon', function (): void {
    refusedScenario($this->tenant, 'scenarios_retroactive_range', ['retroactive_months' => -1]);
    refusedScenario($this->tenant, 'scenarios_retroactive_range', ['retroactive_months' => 37, 'horizon_years' => 3]);
});

it('accepts retroactivity as long as the horizon', function (): void {
    Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'retroactive_months' => 36, 'horizon_years' => 3]);

    expect(Scenario::query()->count())->toBe(1);
});

it('refuses a blank name', function (): void {
    refusedScenario($this->tenant, 'scenarios_name_filled', ['name' => '   ']);
});

it('refuses changes that are not the shape they should be', function (): void {
    refusedScenario($this->tenant, 'scenarios_group_changes_object', ['group_changes' => '[]']);
    refusedScenario($this->tenant, 'scenarios_term_changes_list', ['term_changes' => '{}']);
    refusedScenario($this->tenant, 'scenarios_assumptions_object', ['assumptions' => '[]']);
});
