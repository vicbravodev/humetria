<?php

declare(strict_types=1);

use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * The database holds the rules of the catalog by itself (CLAUDE.md, «Base de datos»): what the forms check is checked
 * again here, so nothing that skips a form can store a benefit that makes no sense.
 */

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
});

/**
 * A row of benefits as the table takes it, to write it past the model.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function rawBenefit(Tenant $tenant, array $override = []): array
{
    $row = array_map(fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value, Benefit::factory()->raw(['tenant_id' => $tenant->id]));

    return [...$row, 'id' => (string) Str::uuid(), 'job_grade_ids' => '[]', ...$override];
}

/** Runs the write inside a savepoint, so the failed statement does not poison the test's transaction. */
function refused(Closure $write, string $constraint): void
{
    expect(fn () => DB::transaction($write))->toThrow(QueryException::class, $constraint);
}

it('stores benefits, terms and premiums with a random UUID v4', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(Str::isUuid($benefit->id, 4))->toBeTrue()
        ->and(Str::isUuid(ContractTerm::factory()->forBenefit($benefit)->create()->id, 4))->toBeTrue()
        ->and(Str::isUuid(WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id])->id, 4))->toBeTrue();
});

it('accepts every kind, frequency, integration and group the enums name', function (): void {
    foreach (BenefitKind::cases() as $kind) {
        Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'kind' => $kind]);
    }

    foreach (Frequency::cases() as $frequency) {
        Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'frequency' => $frequency]);
    }

    foreach (PayGroup::cases() as $group) {
        Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'applies_to_group' => $group]);
    }

    foreach ([SbcIntegration::Always, SbcIntegration::Never] as $integration) {
        Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'sbc_integration' => $integration]);
    }

    Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'sbc_integration' => SbcIntegration::UpToCap, 'sbc_cap_cents' => 4692]);

    expect(Benefit::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(4 + 3 + 2 + 2 + 1);
});

it('refuses a kind, frequency, integration, group or base key it does not know', function (string $column, string $constraint): void {
    refused(fn () => DB::table('benefits')->insert(rawBenefit($this->tenant, [$column => 'unknown'])), $constraint);
})->with([
    'kind' => ['kind', 'benefits_kind_known'],
    'frequency' => ['frequency', 'benefits_frequency_known'],
    'group' => ['applies_to_group', 'benefits_group_known'],
    'integration' => ['sbc_integration', 'benefits_sbc_integration_known'],
    'base key' => ['base_key', 'benefits_base_key_known'],
]);

it('refuses a blank name and a negative number', function (array $override, string $constraint): void {
    refused(fn () => Benefit::factory()->create(['tenant_id' => $this->tenant->id, ...$override]), $constraint);
})->with([
    'blank name' => [['name' => '   '], 'benefits_name_filled'],
    'negative minimum tenure' => [['min_tenure_months' => -1], 'benefits_amounts_not_negative'],
    'negative cap' => [['cap_cents' => -1], 'benefits_amounts_not_negative'],
    'negative SBC cap' => [['sbc_integration' => SbcIntegration::UpToCap, 'sbc_cap_cents' => -1], 'benefits_amounts_not_negative'],
]);

it('wants a cap exactly when the benefit integrates up to a cap', function (array $override): void {
    refused(fn () => Benefit::factory()->create(['tenant_id' => $this->tenant->id, ...$override]), 'benefits_sbc_cap_matches');
})->with([
    'up to a cap without it' => [['sbc_integration' => SbcIntegration::UpToCap, 'sbc_cap_cents' => null]],
    'a cap on a benefit that always integrates' => [['sbc_integration' => SbcIntegration::Always, 'sbc_cap_cents' => 100]],
    'a cap on a benefit that never integrates' => [['sbc_integration' => SbcIntegration::Never, 'sbc_cap_cents' => 100]],
]);

it('lets a base benefit integrate up to the legal cap, which the law sets', function (): void {
    $benefit = Benefit::factory()->base('food_vouchers')->create(['tenant_id' => $this->tenant->id, 'sbc_integration' => SbcIntegration::UpToCap, 'sbc_cap_cents' => null]);

    expect($benefit->sbc_cap_cents)->toBeNull();
});

it('keeps job grades as a list', function (): void {
    refused(fn () => DB::table('benefits')->insert(rawBenefit($this->tenant, ['job_grade_ids' => '{"a": 1}'])), 'benefits_job_grades_list');
});

it('allows one benefit per name in a company, whatever the case and the spaces, and the same name in another', function (): void {
    Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bono de transporte']);

    refused(fn () => Benefit::factory()->create(['tenant_id' => $this->tenant->id, 'name' => '  BONO DE TRANSPORTE ']), 'benefits_tenant_name_unique');
    Benefit::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Bono de transporte']);

    expect(Benefit::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('allows one base benefit per key in a company', function (): void {
    Benefit::factory()->base('vacation')->create(['tenant_id' => $this->tenant->id]);

    refused(fn () => Benefit::factory()->base('vacation')->create(['tenant_id' => $this->tenant->id, 'name' => 'Otro nombre']), 'benefits_tenant_base_key_unique');
    Benefit::factory()->base('vacation')->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(Benefit::query()->withoutGlobalScopes()->count())->toBe(2);
});

it('never archives a base benefit', function (): void {
    refused(fn () => Benefit::factory()->base('vacation')->archived()->create(['tenant_id' => $this->tenant->id]), 'benefits_base_never_archived');
    Benefit::factory()->archived()->create(['tenant_id' => $this->tenant->id]);
});

it('refuses a negative term, a repeated vigencia and a table that is not a list', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $term = fn (array $override = []) => ContractTerm::factory()->forBenefit($benefit)->create(['effective_from' => '2026-01-01', ...$override]);
    $term();

    refused(fn () => $term(['effective_from' => '2027-01-01', 'value' => -1]), 'contract_terms_value_not_negative');
    refused(fn () => $term(), 'contract_terms_benefit_date_unique');
    refused(fn () => DB::table('contract_terms')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id, 'benefit_id' => $benefit->id, 'effective_from' => '2028-01-01', 'value' => 1, 'tenure_table' => '{"a": 1}', 'created_at' => now(), 'updated_at' => now()]), 'contract_terms_table_list');
    $term(['effective_from' => '2027-01-01', 'tenure_table' => [['from_years' => 1, 'value' => 100]]]);
});

it('points a term only to a benefit of its own company', function (): void {
    $foreign = Benefit::factory()->create();

    refused(fn () => ContractTerm::factory()->forBenefit($foreign)->create(['tenant_id' => $this->tenant->id]), 'contract_terms_benefit_id_tenant_id_foreign');
});

it('keeps the rate of the work risk premium between the minimum and the maximum of the law, one per vigencia', function (): void {
    WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-01-01', 'rate_scaled' => 50_000]);
    WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2027-01-01', 'rate_scaled' => 1_500_000]);

    refused(fn () => WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2028-01-01', 'rate_scaled' => 49_999]), 'work_risk_premiums_rate_in_law');
    refused(fn () => WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2028-01-01', 'rate_scaled' => 1_500_001]), 'work_risk_premiums_rate_in_law');
    refused(fn () => WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id, 'effective_from' => '2026-01-01']), 'work_risk_premiums_tenant_date_unique');
    WorkRiskPremium::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'effective_from' => '2026-01-01']);
});

it('keeps benefits, terms and premiums apart per company', function (): void {
    $mine = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $myTerm = ContractTerm::factory()->forBenefit($mine)->create();
    $myPremium = WorkRiskPremium::factory()->create(['tenant_id' => $this->tenant->id]);
    $foreign = Benefit::factory()->create();
    $foreignTerm = ContractTerm::factory()->forBenefit($foreign)->create();
    $foreignPremium = WorkRiskPremium::factory()->create();
    workIn($this->tenant);

    expect(Benefit::query()->pluck('id')->all())->toBe([$mine->id])
        ->and(ContractTerm::query()->pluck('id')->all())->toBe([$myTerm->id])
        ->and(WorkRiskPremium::query()->pluck('id')->all())->toBe([$myPremium->id])
        ->and(Benefit::query()->whereKey($foreign->id)->update(['name' => 'Robada']))->toBe(0)
        ->and(ContractTerm::query()->whereKey($foreignTerm->id)->update(['value' => 1]))->toBe(0)
        ->and(WorkRiskPremium::query()->whereKey($foreignPremium->id)->update(['rate_scaled' => 60_000]))->toBe(0)
        ->and(Benefit::query()->withoutGlobalScopes()->findOrFail($foreign->id)->name)->not->toBe('Robada');
});
