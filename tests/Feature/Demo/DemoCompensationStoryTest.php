<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Enums\BaseBenefit;
use App\Modules\Compensation\Enums\PayGroup;
use App\Modules\Compensation\Models\Benefit;
use App\Modules\Compensation\Models\ContractTerm;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Compensation\Models\WorkRiskPremium;
use App\Modules\Compensation\Support\BaseCatalog;
use App\Modules\Compensation\Support\Calculation\ScenarioCost;
use App\Modules\Compensation\Support\ReportBuilder;
use App\Modules\Compensation\Support\ScenarioInputBuilder;
use App\Modules\Demos\Contracts\DemoWorkspace;
use App\Modules\Leave\Models\LeavePolicy;
use App\Modules\Organization\Enums\StaffGroup;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\Demo\DemoClock;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

it('tells the compensation story: a tabulator, a collective contract above the law and two proposals with their cost', function (): void {
    // In production the jobs run later on the queue: the story must stand without them.
    Bus::fake();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.' '.json_encode($log->context);
    });

    resolve(DemoWorkspace::class)->prepare();

    $tenantId = resolve(DemoCompanies::class)->id();
    $today = CarbonImmutable::now(DemoClock::TIMEZONE)->startOfDay();

    // puts the rank and file in the collective contract and everyone else in trust, so the whole roster is costed
    $people = collect(DemoRoster::people())->keyBy(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key));
    foreach (Employee::query()->get() as $employee) {
        expect($employee->staff_group)->toBe($people[$employee->id]->category === 'Operativo' ? StaffGroup::Unionized : StaffGroup::Trusted);
    }

    // pays every job grade of the roster by the tabulator, above the minimum wage and higher the higher the grade
    $grades = JobGrade::query()->get()->keyBy('id');
    $salaries = GradeSalary::query()->get();
    $daily = $salaries->mapWithKeys(fn (GradeSalary $salary): array => [$grades[$salary->job_grade_id]->level => $salary->daily_salary_cents]);
    expect($salaries->pluck('job_grade_id')->sort()->values()->all())->toBe($grades->keys()->sort()->values()->all())
        ->and($salaries->every(fn (GradeSalary $salary): bool => $salary->effective_from->lessThan($today)))->toBeTrue()
        ->and($daily->min())->toBeGreaterThan(31504)
        ->and($daily['O1'] < $daily['O3'] && $daily['O3'] < $daily['J1'] && $daily['J1'] < $daily['G1'] && $daily['G1'] < $daily['D1'])->toBeTrue();

    // has the base benefits with the law's terms first and the contract's above them since, and a benefit of its own
    $benefits = Benefit::query()->get();
    $base = $benefits->whereNotNull('base_key')->keyBy('base_key');
    $current = fn (Benefit $benefit): ContractTerm => ContractTerm::query()->where('benefit_id', $benefit->id)->orderByDesc('effective_from')->firstOrFail();
    expect($base->keys()->sort()->values()->all())->toBe(collect(BaseBenefit::cases())->map->value->sort()->values()->all())
        ->and($current($base['christmas_bonus'])->value)->toBeGreaterThan(1500)
        ->and($current($base['vacation_premium'])->value)->toBeGreaterThan(2500)
        // the same prima vacacional Vacaciones pays everyone today (a percent is stored in hundredths here, whole there)
        ->and($current($base['vacation_premium'])->value)->toBe(LeavePolicy::query()->sole()->premium_percent * 100)
        ->and($current($base['food_vouchers'])->value)->toBeGreaterThan(0)
        ->and($current($base['savings_fund'])->value)->toBeGreaterThan(0)
        ->and(ContractTerm::query()->where('benefit_id', $base['christmas_bonus']->id)->count())->toBe(2);
    $own = $benefits->whereNull('base_key')->sole();
    expect($own->applies_to_group)->toBe(PayGroup::Unionized)
        ->and($current($own)->value)->toBeGreaterThan(0)
        ->and(WorkRiskPremium::query()->sole()->effective_from->lessThan($today))->toBeTrue();

    // has two proposals for the next revision, the union's and the company's, each costed by the module over the whole roster
    $scenarios = Scenario::query()->orderBy('created_at')->get();
    expect($scenarios)->toHaveCount(2)
        ->and($scenarios->every(fn (Scenario $scenario): bool => $scenario->effective_on->greaterThan($today) && $scenario->term_changes !== []))->toBeTrue();
    [$union, $company] = $scenarios->map(fn (Scenario $scenario) => ScenarioCost::firstYear(resolve(ScenarioInputBuilder::class)->build($scenario)))->all();
    foreach ([$union, $company] as $cost) {
        expect($cost->people)->toBe(180)
            ->and($cost->gaps->costed)->toBe(180)
            ->and($cost->uncostedGrades)->toBe([])
            ->and($cost->uncalculatedLines)->toBe([])
            ->and($cost->beforeCents)->toBeGreaterThan(0)
            ->and($cost->afterCents)->toBeGreaterThan($cost->beforeCents);
    }
    // the union asks for 50 % of prima vacacional; the company keeps the 30 % in force
    $premiums = $scenarios->map(fn (Scenario $scenario): ?int => collect($scenario->term_changes)->firstWhere('benefit_id', $base['vacation_premium']->id)['value'] ?? null)->all();
    expect($premiums)->toBe([5000, null]);
    expect($union->beforeCents)->toBe($company->beforeCents)
        ->and($union->afterCents)->toBeGreaterThan($company->afterCents);
    // the company's proposal raises both groups; the union's, only its own
    $byGroup = fn ($cost): array => collect($cost->byGroup)->mapWithKeys(fn ($cut): array => [$cut->key => $cut->deltaCents()])->all();
    expect($byGroup($company)['trusted'])->toBeGreaterThan(0)
        ->and($byGroup($union)['unionized'])->toBeGreaterThan($byGroup($company)['unionized']);
    foreach ($scenarios as $scenario) {
        expect(resolve(ReportBuilder::class)->build($scenario))->toBeArray();
    }

    // keeps salaries and terms out of the logs and the audit trail (a number among ids, as a 5000 group of a UUID v5, is no amount)
    $money = [...$salaries->pluck('daily_salary_cents')->all(), ...ContractTerm::query()->where('value', '>', 0)->pluck('value')->all(), ...$scenarios->flatMap(fn (Scenario $scenario): array => array_column($scenario->term_changes, 'value'))->all()];
    $text = implode("\n", $logged)."\n".AuditEntry::query()->where('tenant_id', $tenantId)->get()->map(fn (AuditEntry $entry): string => $entry->event.' '.json_encode($entry->metadata))->implode("\n");
    expect($money)->not->toBeEmpty();
    foreach (array_unique($money) as $cents) {
        expect(preg_match('/(?<![\w.-])'.$cents.'(?![\w.-])/', $text))->toBe(0);
    }

    // nothing outside the demo company
    foreach ([Benefit::class, ContractTerm::class, GradeSalary::class, Scenario::class, WorkRiskPremium::class] as $model) {
        expect($model::query()->withoutGlobalScopes()->where('tenant_id', '!=', $tenantId)->count())->toBe(0);
    }

    // The base benefits of the story are what the panel's catalog writes the first time it opens, the same day: the same
    // benefits and the same first terms of the law.
    $shape = fn (): array => Benefit::query()->whereNotNull('base_key')->orderBy('base_key')->get()->map(function (Benefit $benefit): array {
        $first = ContractTerm::query()->where('benefit_id', $benefit->id)->orderBy('effective_from')->firstOrFail();

        return [
            array_diff_key($benefit->attributesToArray(), array_flip(['id', 'tenant_id', 'created_at', 'updated_at'])),
            [$first->effective_from->toDateString(), $first->value, $first->tenure_table],
        ];
    })->all();
    $story = $shape();
    $opened = ContractTerm::query()->where('benefit_id', $base['christmas_bonus']->id)->orderBy('created_at')->firstOrFail()->created_at;
    workIn(Tenant::factory()->create());
    resolve(BaseCatalog::class)->ensure($opened);
    expect($story)->toEqual($shape());
});
