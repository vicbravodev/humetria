<?php

declare(strict_types=1);

use App\Modules\Compensation\Actions\SaveScenario;
use App\Modules\Compensation\Data\ScenarioData;
use App\Modules\Compensation\Data\TermChange;
use App\Modules\Compensation\Enums\AssumptionKey;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

mutates(SaveScenario::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/** @param  array<string, mixed>  $override */
function scenarioData(array $override = []): ScenarioData
{
    return new ScenarioData(...[
        'id' => null,
        'name' => 'Revisión 2027',
        'effectiveOn' => CarbonImmutable::parse('2027-01-01'),
        'retroactiveMonths' => 0,
        'horizonYears' => 3,
        'unionizedRaiseBp' => 500,
        'trustedRaiseBp' => null,
        'termChanges' => [],
        'assumptions' => [],
        'defaultState' => null,
        ...$override,
    ]);
}

/** The errors, by field, a save is refused with. @return array<string, list<string>> */
function scenarioRefusedWith(ScenarioData $data): array
{
    try {
        resolve(SaveScenario::class)->handle($data);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('saves a scenario with changes per group and per benefit', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);

    $scenario = resolve(SaveScenario::class)->handle(scenarioData([
        'name' => '  Revisión 2027 ', 'retroactiveMonths' => 2, 'horizonYears' => 4, 'unionizedRaiseBp' => 575, 'trustedRaiseBp' => 300, 'defaultState' => 'JAL',
        'termChanges' => [new TermChange($benefit->id, 250000, null)],
    ]));
    $saved = Scenario::query()->findOrFail($scenario->id);

    expect($saved->tenant_id)->toBe($this->tenant->id)
        ->and($saved->name)->toBe('Revisión 2027')
        ->and($saved->effective_on->toDateString())->toBe('2027-01-01')
        ->and($saved->retroactive_months)->toBe(2)
        ->and($saved->horizon_years)->toBe(4)
        ->and($saved->group_changes)->toEqual(['unionized' => 575, 'trusted' => 300])
        ->and($saved->term_changes)->toEqual([['benefit_id' => $benefit->id, 'value' => 250000, 'tenure_table' => null]])
        ->and($saved->default_state)->toBe('JAL')
        ->and($saved->archived_at)->toBeNull();
});

it('copies the unionized raise to the trusted unless they have their own', function (): void {
    $copy = resolve(SaveScenario::class)->handle(scenarioData(['trustedRaiseBp' => null]));
    $own = resolve(SaveScenario::class)->handle(scenarioData(['name' => 'Otra', 'trustedRaiseBp' => 0]));

    expect($copy->group_changes['trusted'])->toBeNull()->and($own->group_changes['trusted'])->toBe(0);
});

it('keeps the new table of a benefit that grows with seniority', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $table = [['from_years' => 1, 'value' => 1400], ['from_years' => 5, 'value' => 2200]];

    $scenario = resolve(SaveScenario::class)->handle(scenarioData(['termChanges' => [new TermChange($benefit->id, 1200, $table)]]));

    expect($scenario->term_changes[0]['tenure_table'])->toBe($table);
});

it('keeps the platform assumptions, with their source and date, when none is given', function (): void {
    $scenario = resolve(SaveScenario::class)->handle(scenarioData());

    expect($scenario->assumptions)->toBe(AssumptionKey::defaultSnapshot())
        ->and($scenario->assumptions['inflation']['source'])->toContain('Banco de México')
        ->and($scenario->assumptions['inflation']['set_on'])->toBe('2026-11-04');
});

it('marks an assumption the person changed as theirs, with the day they set it', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-15 10:00', config()->string('app.display_timezone')));

    $scenario = resolve(SaveScenario::class)->handle(scenarioData(['assumptions' => ['turnover' => 800, 'inflation' => 300]]));

    expect($scenario->assumptions['turnover'])->toBe(['value_bp' => 800, 'source' => 'Capturado en este escenario.', 'set_on' => '2026-12-15'])
        // 300 is the platform's own value, so it keeps the platform's source and date.
        ->and($scenario->assumptions['inflation'])->toBe(AssumptionKey::defaultSnapshot()['inflation']);
});

it('keeps the source and date of an assumption that was not changed when the scenario is edited', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-12-15', config()->string('app.display_timezone')));
    $scenario = resolve(SaveScenario::class)->handle(scenarioData(['assumptions' => ['turnover' => 800]]));

    $this->travelTo(CarbonImmutable::parse('2027-03-01', config()->string('app.display_timezone')));
    $edited = resolve(SaveScenario::class)->handle(scenarioData(['id' => $scenario->id, 'assumptions' => ['turnover' => 800, 'headcount_growth' => 200]]));

    expect($edited->assumptions['turnover']['set_on'])->toBe('2026-12-15')
        ->and($edited->assumptions['headcount_growth']['set_on'])->toBe('2027-03-01');
});

it('goes back to the platform value of an assumption left out of an edit', function (): void {
    $scenario = resolve(SaveScenario::class)->handle(scenarioData(['assumptions' => ['turnover' => 800]]));
    $edited = resolve(SaveScenario::class)->handle(scenarioData(['id' => $scenario->id, 'assumptions' => []]));

    expect($edited->assumptions['turnover'])->toBe(AssumptionKey::defaultSnapshot()['turnover']);
});

it('edits a scenario without making another', function (): void {
    $scenario = resolve(SaveScenario::class)->handle(scenarioData());
    $edited = resolve(SaveScenario::class)->handle(scenarioData(['id' => $scenario->id, 'name' => 'Otro nombre', 'unionizedRaiseBp' => 600]));

    expect($edited->id)->toBe($scenario->id)->and(Scenario::query()->count())->toBe(1)
        ->and($edited->name)->toBe('Otro nombre')->and($edited->group_changes['unionized'])->toBe(600);
});

it('does not edit the scenario of another company', function (): void {
    $foreign = Scenario::factory()->create();

    expect(fn () => resolve(SaveScenario::class)->handle(scenarioData(['id' => $foreign->id])))->toThrow(ModelNotFoundException::class);
});

it('refuses a blank name and one over 120 characters', function (): void {
    expect(scenarioRefusedWith(scenarioData(['name' => '   '])))->toHaveKey('name')
        ->and(scenarioRefusedWith(scenarioData(['name' => str_repeat('a', 121)])))->toHaveKey('name')
        ->and(scenarioRefusedWith(scenarioData(['name' => str_repeat('a', 120)])))->toBe([]);
});

it('refuses a horizon of 2 or 6 and accepts 3 and 5', function (): void {
    expect(scenarioRefusedWith(scenarioData(['horizonYears' => 2])))->toHaveKey('horizon_years')
        ->and(scenarioRefusedWith(scenarioData(['horizonYears' => 6])))->toHaveKey('horizon_years')
        ->and(scenarioRefusedWith(scenarioData(['name' => 'Tres', 'horizonYears' => 3])))->toBe([])
        ->and(scenarioRefusedWith(scenarioData(['name' => 'Cinco', 'horizonYears' => 5])))->toBe([]);
});

it('refuses retroactivity longer than the horizon and a negative one', function (): void {
    expect(scenarioRefusedWith(scenarioData(['horizonYears' => 3, 'retroactiveMonths' => 37])))->toHaveKey('retroactive_months')
        ->and(scenarioRefusedWith(scenarioData(['retroactiveMonths' => -1])))->toHaveKey('retroactive_months')
        ->and(scenarioRefusedWith(scenarioData(['horizonYears' => 3, 'retroactiveMonths' => 36])))->toBe([])
        ->and(scenarioRefusedWith(scenarioData(['name' => 'Cero', 'retroactiveMonths' => 0])))->toBe([]);
});

it('refuses an effective date before the terms of the contract and before the fiscal values the platform has', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    ContractTerm::factory()->create(['tenant_id' => $this->tenant->id, 'benefit_id' => $benefit->id, 'effective_from' => '2026-01-01']);

    expect(scenarioRefusedWith(scenarioData(['effectiveOn' => CarbonImmutable::parse('2025-12-31')])))->toHaveKey('effective_on')
        ->and(scenarioRefusedWith(scenarioData(['effectiveOn' => CarbonImmutable::parse('2026-01-01')])))->toBe([])
        ->and(scenarioRefusedWith(scenarioData(['name' => 'Vieja', 'effectiveOn' => CarbonImmutable::parse('2022-12-31')])))->toHaveKey('effective_on');
});

it('lets a scenario start before 2026 when the company has no terms and the platform has the values', function (): void {
    expect(scenarioRefusedWith(scenarioData(['effectiveOn' => CarbonImmutable::parse('2023-01-01')])))->toBe([])
        ->and(scenarioRefusedWith(scenarioData(['name' => 'Lejana', 'effectiveOn' => CarbonImmutable::now()->addYears(11)])))->toHaveKey('effective_on');
});

it('refuses a raise below zero or over 100 percent, in either group', function (): void {
    expect(scenarioRefusedWith(scenarioData(['unionizedRaiseBp' => -1])))->toHaveKey('unionized_raise')
        ->and(scenarioRefusedWith(scenarioData(['unionizedRaiseBp' => 10001])))->toHaveKey('unionized_raise')
        ->and(scenarioRefusedWith(scenarioData(['trustedRaiseBp' => -1])))->toHaveKey('trusted_raise')
        ->and(scenarioRefusedWith(scenarioData(['trustedRaiseBp' => 10001])))->toHaveKey('trusted_raise')
        ->and(scenarioRefusedWith(scenarioData(['unionizedRaiseBp' => 10000, 'trustedRaiseBp' => 0])))->toBe([]);
});

it('refuses a state the platform does not know', function (): void {
    expect(scenarioRefusedWith(scenarioData(['defaultState' => 'XXX'])))->toHaveKey('default_state');
});

it('refuses an assumption out of its range', function (): void {
    $out = [
        'inflation' => [-1, 5001], 'turnover' => [-1, 10001], 'headcount_growth' => [-5001, 5001],
        'future_revisions' => [-1, 5001], 'following_years_rate' => [-1, 5001],
    ];

    foreach ($out as $key => $values) {
        foreach ($values as $value) {
            expect(scenarioRefusedWith(scenarioData(['assumptions' => [$key => $value]])))->toHaveKey("assumptions.{$key}");
        }
    }

    expect(scenarioRefusedWith(scenarioData(['assumptions' => ['headcount_growth' => -5000, 'turnover' => 10000]])))->toBe([]);
});

it('refuses an assumption that does not exist', function (): void {
    expect(scenarioRefusedWith(scenarioData(['assumptions' => ['luck' => 1]])))->toHaveKey('assumptions');
});

it('refuses a change to a benefit that is not the company\'s, is archived, is repeated or has a bad value or table', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $archived = Benefit::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
    $foreign = Benefit::factory()->create();

    expect(scenarioRefusedWith(scenarioData(['termChanges' => [new TermChange($foreign->id, 100, null)]])))->toHaveKey('term_changes')
        ->and(scenarioRefusedWith(scenarioData(['termChanges' => [new TermChange($archived->id, 100, null)]])))->toHaveKey('term_changes')
        ->and(scenarioRefusedWith(scenarioData(['termChanges' => [new TermChange($benefit->id, 100, null), new TermChange($benefit->id, 200, null)]])))->toHaveKey('term_changes')
        ->and(scenarioRefusedWith(scenarioData(['termChanges' => [new TermChange($benefit->id, -1, null)]])))->toHaveKey('term_changes')
        ->and(scenarioRefusedWith(scenarioData(['termChanges' => [new TermChange($benefit->id, 100, [['from_years' => 5, 'value' => 1], ['from_years' => 5, 'value' => 2]])]])))->toHaveKey('term_changes')
        ->and(scenarioRefusedWith(scenarioData(['termChanges' => [new TermChange($benefit->id, 100, null)]])))->toBe([]);
});

it('writes a log with the ids and no raise or amount', function (): void {
    Log::spy();
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);

    $scenario = resolve(SaveScenario::class)->handle(scenarioData(['unionizedRaiseBp' => 575, 'termChanges' => [new TermChange($benefit->id, 250000, null)]]));
    resolve(SaveScenario::class)->handle(scenarioData(['id' => $scenario->id]));

    Log::shouldHaveReceived('info')->with('compensation.scenario_saved', ['scenario_id' => $scenario->id, 'created' => true])->once();
    Log::shouldHaveReceived('info')->with('compensation.scenario_saved', ['scenario_id' => $scenario->id, 'created' => false])->once();
});
