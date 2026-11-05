<?php

declare(strict_types=1);

use App\Modules\Compensation\Actions\ArchiveBenefit;
use App\Modules\Compensation\Actions\RestoreBenefit;
use App\Modules\Compensation\Actions\SaveBenefit;
use App\Modules\Compensation\Actions\SaveContractTerm;
use App\Modules\Compensation\Actions\SaveWorkRiskPremium;
use App\Modules\Compensation\Data\BenefitData;
use App\Modules\Compensation\Data\TermData;
use App\Modules\Compensation\Enums\BenefitKind;
use App\Modules\Compensation\Enums\Frequency;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Enums\SbcIntegration;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

mutates(SaveBenefit::class, SaveContractTerm::class, SaveWorkRiskPremium::class, ArchiveBenefit::class, RestoreBenefit::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/**
 * @param  array<string, mixed>  $override
 */
function benefitData(array $override = []): BenefitData
{
    return new BenefitData(...[
        'id' => null,
        'name' => 'Bono de transporte',
        'kind' => BenefitKind::FixedAmount,
        'frequency' => Frequency::Monthly,
        'group' => null,
        'jobGradeIds' => [],
        'minTenureMonths' => 0,
        'sbcIntegration' => SbcIntegration::Always,
        'sbcCapCents' => null,
        'inPayrollTax' => true,
        'inPtuBase' => false,
        'inSeniorityBase' => false,
        'incomeTaxExempt' => false,
        'capCents' => null,
        ...$override,
    ]);
}

function termData(string $from = '2026-01-01', int $value = 150000, ?array $table = null): TermData
{
    return new TermData(CarbonImmutable::parse($from), $value, $table);
}

/** The message of the validation error a write is refused with, by field. */
function refusedWith(Closure $write): array
{
    try {
        $write();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('creates a custom benefit from its attributes alone, with its first term', function (): void {
    $grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);

    $benefit = resolve(SaveBenefit::class)->handle(benefitData([
        'group' => PayGroup::Unionized,
        'jobGradeIds' => [$grade->id],
        'minTenureMonths' => 12,
        'sbcIntegration' => SbcIntegration::UpToCap,
        'sbcCapCents' => 20000,
        'inPtuBase' => true,
        'incomeTaxExempt' => true,
        'capCents' => 500000,
    ]), termData());

    $saved = Benefit::query()->findOrFail($benefit->id);

    expect($saved->tenant_id)->toBe($this->tenant->id)
        ->and($saved->name)->toBe('Bono de transporte')
        ->and($saved->base_key)->toBeNull()
        ->and($saved->kind)->toBe(BenefitKind::FixedAmount)
        ->and($saved->frequency)->toBe(Frequency::Monthly)
        ->and($saved->applies_to_group)->toBe(PayGroup::Unionized)
        ->and($saved->job_grade_ids)->toBe([$grade->id])
        ->and($saved->min_tenure_months)->toBe(12)
        ->and($saved->sbc_integration)->toBe(SbcIntegration::UpToCap)
        ->and($saved->sbc_cap_cents)->toBe(20000)
        ->and($saved->in_payroll_tax)->toBeTrue()
        ->and($saved->in_ptu_base)->toBeTrue()
        ->and($saved->in_seniority_base)->toBeFalse()
        ->and($saved->income_tax_exempt)->toBeTrue()
        ->and($saved->cap_cents)->toBe(500000)
        ->and($saved->archived_at)->toBeNull()
        ->and($saved->terms()->sole()->value)->toBe(150000)
        ->and($saved->terms()->sole()->effective_from->toDateString())->toBe('2026-01-01');
});

it('creates the benefit without a term when none is given', function (): void {
    $benefit = resolve(SaveBenefit::class)->handle(benefitData());

    expect($benefit->terms()->count())->toBe(0);
});

it('edits a custom benefit and keeps its terms', function (): void {
    $benefit = resolve(SaveBenefit::class)->handle(benefitData(), termData());

    $edited = resolve(SaveBenefit::class)->handle(benefitData(['id' => $benefit->id, 'name' => 'Bono de despensa', 'kind' => BenefitKind::UmaMultiple, 'inPayrollTax' => false]));

    expect(Benefit::query()->count())->toBe(1)
        ->and($edited->id)->toBe($benefit->id)
        ->and($edited->name)->toBe('Bono de despensa')
        ->and($edited->kind)->toBe(BenefitKind::UmaMultiple)
        ->and($edited->in_payroll_tax)->toBeFalse()
        ->and($edited->terms()->count())->toBe(1);
});

it('drops a cap that goes with no integration up to a cap', function (): void {
    $benefit = resolve(SaveBenefit::class)->handle(benefitData(['sbcIntegration' => SbcIntegration::Never, 'sbcCapCents' => 100]));

    expect($benefit->sbc_cap_cents)->toBeNull();
});

it('refuses a negative cap, a cap below zero in the SBC, a negative tenure and a blank name', function (array $override, string $field): void {
    expect(array_keys(refusedWith(fn () => resolve(SaveBenefit::class)->handle(benefitData($override)))))->toBe([$field])
        ->and(Benefit::query()->count())->toBe(0);
})->with([
    'a cap below zero' => [['capCents' => -1], 'cap'],
    'an SBC cap below zero' => [['sbcIntegration' => SbcIntegration::UpToCap, 'sbcCapCents' => -1], 'sbc_cap'],
    'up to a cap without it' => [['sbcIntegration' => SbcIntegration::UpToCap, 'sbcCapCents' => null], 'sbc_cap'],
    'a negative tenure' => [['minTenureMonths' => -1], 'min_tenure_months'],
    'a blank name' => [['name' => '   '], 'name'],
]);

it('accepts a cap of zero', function (): void {
    expect(resolve(SaveBenefit::class)->handle(benefitData(['capCents' => 0]))->cap_cents)->toBe(0);
});

it('refuses a name that another benefit has, whatever the case and the spaces, but not its own', function (): void {
    $benefit = resolve(SaveBenefit::class)->handle(benefitData());

    expect(array_keys(refusedWith(fn () => resolve(SaveBenefit::class)->handle(benefitData(['name' => ' BONO de transporte '])))))->toBe(['name'])
        ->and(resolve(SaveBenefit::class)->handle(benefitData(['id' => $benefit->id, 'name' => 'BONO DE TRANSPORTE']))->name)->toBe('BONO DE TRANSPORTE');
});

it('allows the name another company uses', function (): void {
    Benefit::factory()->create(['name' => 'Bono de transporte']);

    expect(resolve(SaveBenefit::class)->handle(benefitData())->name)->toBe('Bono de transporte');
});

it('refuses the name of a base benefit', function (): void {
    Benefit::factory()->base('vacation')->create(['tenant_id' => $this->tenant->id]);

    expect(array_keys(refusedWith(fn () => resolve(SaveBenefit::class)->handle(benefitData(['name' => 'Vacaciones'])))))->toBe(['name']);
});

it('refuses job grades of another company, ones that do not exist and ids that are not ids', function (): void {
    $foreign = JobGrade::factory()->create();
    $mine = JobGrade::factory()->create(['tenant_id' => $this->tenant->id]);

    foreach ([[$foreign->id], [$mine->id, $foreign->id], [(string) Str::uuid()], ['not-an-id']] as $ids) {
        expect(array_keys(refusedWith(fn () => resolve(SaveBenefit::class)->handle(benefitData(['jobGradeIds' => $ids])))))->toBe(['job_grade_ids']);
    }

    expect(Benefit::query()->count())->toBe(0)
        ->and(resolve(SaveBenefit::class)->handle(benefitData(['jobGradeIds' => [$mine->id, $mine->id]]))->job_grade_ids)->toBe([$mine->id]);
});

it('refuses to edit a base benefit and to edit one of another company', function (): void {
    $base = Benefit::factory()->base('vacation')->create(['tenant_id' => $this->tenant->id]);
    $foreign = Benefit::factory()->create();

    expect(fn () => resolve(SaveBenefit::class)->handle(benefitData(['id' => $base->id, 'name' => 'Otra'])))->toThrow(ValidationException::class)
        ->and(Benefit::query()->findOrFail($base->id)->name)->toBe('Vacaciones')
        ->and(fn () => resolve(SaveBenefit::class)->handle(benefitData(['id' => $foreign->id])))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('saves a term, and replaces the one of the same date', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);

    $first = resolve(SaveContractTerm::class)->handle($benefit, termData('2026-01-01', 1500));
    $second = resolve(SaveContractTerm::class)->handle($benefit, termData('2027-01-01', 1700, [['from_years' => 1, 'value' => 1700]]));
    $replaced = resolve(SaveContractTerm::class)->handle($benefit, termData('2026-01-01', 1600));

    expect(ContractTerm::query()->count())->toBe(2)
        ->and($replaced->id)->toBe($first->id)
        ->and($replaced->value)->toBe(1600)
        ->and($replaced->tenant_id)->toBe($this->tenant->id)
        ->and($second->tenure_table)->toEqual([['from_years' => 1, 'value' => 1700]])
        ->and($first->fresh()?->tenure_table)->toBeNull();
});

it('keeps a table with no rows as no table', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(resolve(SaveContractTerm::class)->handle($benefit, termData(table: []))->tenure_table)->toBeNull();
});

it('refuses a negative term and a table out of order', function (): void {
    $benefit = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);

    expect(array_keys(refusedWith(fn () => resolve(SaveContractTerm::class)->handle($benefit, termData(value: -1)))))->toBe(['value'])
        ->and(array_keys(refusedWith(fn () => resolve(SaveContractTerm::class)->handle($benefit, termData(table: [['from_years' => 5, 'value' => 1], ['from_years' => 1, 'value' => 1]])))))->toBe(['tenure_table'])
        ->and(ContractTerm::query()->count())->toBe(0);
});

it('saves the work risk premium of a vigencia and replaces the one of the same date', function (): void {
    $first = resolve(SaveWorkRiskPremium::class)->handle(CarbonImmutable::parse('2026-01-01'), 113_065);
    resolve(SaveWorkRiskPremium::class)->handle(CarbonImmutable::parse('2027-01-01'), 50_000);
    $replaced = resolve(SaveWorkRiskPremium::class)->handle(CarbonImmutable::parse('2026-01-01'), 1_500_000);

    expect(WorkRiskPremium::query()->count())->toBe(2)
        ->and($replaced->id)->toBe($first->id)
        ->and($replaced->rate_scaled)->toBe(1_500_000)
        ->and($replaced->tenant_id)->toBe($this->tenant->id);
});

it('refuses a premium below 0.5 percent and above 15 percent', function (int $rate): void {
    expect(array_keys(refusedWith(fn () => resolve(SaveWorkRiskPremium::class)->handle(CarbonImmutable::parse('2026-01-01'), $rate))))->toBe(['rate'])
        ->and(WorkRiskPremium::query()->count())->toBe(0);
})->with([49_999, 1_500_001, 0, -1]);

it('archives a custom benefit and restores it, and never archives a base one', function (): void {
    $custom = Benefit::factory()->create(['tenant_id' => $this->tenant->id]);
    $base = Benefit::factory()->base('vacation')->create(['tenant_id' => $this->tenant->id]);
    $this->travelTo('2026-11-03 12:00:00');

    $archived = resolve(ArchiveBenefit::class)->handle($custom);

    expect($archived->archived_at?->toDateTimeString())->toBe('2026-11-03 12:00:00')
        ->and(fn () => resolve(ArchiveBenefit::class)->handle($base))->toThrow(ValidationException::class)
        ->and(Benefit::query()->findOrFail($base->id)->archived_at)->toBeNull();

    expect(resolve(RestoreBenefit::class)->handle($archived)->archived_at)->toBeNull();
});

it('writes a log with ids only, never an amount', function (): void {
    Log::spy();

    $benefit = resolve(SaveBenefit::class)->handle(benefitData(['sbcIntegration' => SbcIntegration::UpToCap, 'sbcCapCents' => 123457, 'capCents' => 765431]), termData(value: 987654));
    $term = resolve(SaveContractTerm::class)->handle($benefit, termData('2027-01-01', 876543));
    $premium = resolve(SaveWorkRiskPremium::class)->handle(CarbonImmutable::parse('2026-01-01'), 113_065);
    resolve(ArchiveBenefit::class)->handle($benefit);
    resolve(RestoreBenefit::class)->handle($benefit);
    resolve(SaveBenefit::class)->handle(benefitData(['id' => $benefit->id, 'name' => 'Otro nombre', 'sbcIntegration' => SbcIntegration::Always]));

    Log::shouldHaveReceived('info')->with('compensation.benefit_saved', ['benefit_id' => $benefit->id, 'created' => true])->once();
    Log::shouldHaveReceived('info')->with('compensation.benefit_saved', ['benefit_id' => $benefit->id, 'created' => false])->once();
    Log::shouldHaveReceived('info')->with('compensation.term_saved', ['benefit_id' => $benefit->id, 'term_id' => $term->id])->once();
    Log::shouldHaveReceived('info')->with('compensation.risk_premium_saved', ['risk_premium_id' => $premium->id])->once();
    Log::shouldHaveReceived('info')->with('compensation.benefit_archived', ['benefit_id' => $benefit->id])->once();
    Log::shouldHaveReceived('info')->with('compensation.benefit_restored', ['benefit_id' => $benefit->id])->once();
});
