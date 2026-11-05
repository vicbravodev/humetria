<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Compensation\Http\Controllers\SharedReportController;
use App\Modules\Compensation\Models\GradeSalary;
use App\Modules\Compensation\Models\ReportShare;
use App\Modules\Compensation\Models\SalaryOverride;
use App\Modules\Compensation\Models\Scenario;
use App\Modules\Organization\Models\Area;
use App\Modules\Organization\Models\Employee;
use App\Modules\Organization\Models\JobGrade;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SharedReportController::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-04 10:00:00');
    config(['results.min_group' => 5]);
    $this->tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Acme']);
    workIn($this->tenant);
    $this->grade = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Operador', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'daily_salary_cents' => 50_000, 'effective_from' => '2026-01-01']);
    $this->big = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Producción']);
    $this->small = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Dirección']);
    $this->medium = Area::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Almacén']);
    foreach ([[$this->big, 20], [$this->medium, 6], [$this->small, 2]] as [$area, $count]) {
        foreach (range(1, $count) as $number) {
            Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $this->grade->id, 'area_id' => $area->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'state' => 'JAL', 'employee_number' => "{$area->name}-{$number}"]);
        }
    }
    $this->scenario = Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Revisión 2027']);
    $this->token = str_repeat('a', 64);
    $this->share = ReportShare::factory()->create(['scenario_id' => $this->scenario->id, 'tenant_id' => $this->tenant->id, 'token_hash' => hash('sha256', $this->token), 'created_at' => now(), 'expires_at' => now()->addDays(2)]);
});

it('opens the report from the link with no account, shows who shared it and when it ends, and records each opening', function (): void {
    $response = $this->get(route('compensation.report.shared', $this->token))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn (Assert $page) => $page
            ->component('compensation/shared-report')
            ->where('company', 'Acme')
            ->where('expiresAt', '6 nov 2026, 04:00')
            ->where('report.scenario.name', 'Revisión 2027')
            ->where('report.headline.people', 28)
            ->where('report.can_annex', false)
            ->has('report.concepts')->has('report.months', 12)->has('report.projection', 3)->has('report.assumptions', 5));

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(AuditEntry::query()->where('event', 'compensation.share_opened')->sole())
        ->tenant_id->toBe($this->tenant->id)->actor_id->toBeNull()
        ->metadata->toEqual(['scenario_id' => $this->scenario->id, 'share_id' => $this->share->id]);

    $this->get(route('compensation.report.shared', $this->token))->assertOk();
    expect(AuditEntry::query()->where('event', 'compensation.share_opened')->count())->toBe(2);
});

it('folds the small areas into Others and keeps the totals exact', function (): void {
    $this->get(route('compensation.report.shared', $this->token))
        ->assertInertia(fn (Assert $page) => $page
            // Dirección has 2 people: alone under the minimum, and Others of 2 would be given away by the total, so Almacén (6) joins it.
            ->where('report.cuts.areas', fn ($areas): bool => collect($areas)->pluck('label')->all() === ['Producción', 'Otros']
                && collect($areas)->pluck('people')->all() === [20, 8])
            ->where('report.cuts.grades', [])
            ->where('report.cuts.groups', []));
});

it('shows a cut that has two or more groups over the minimum as it is', function (): void {
    Employee::query()->where('area_id', $this->small->id)->update(['area_id' => $this->medium->id]);

    $this->get(route('compensation.report.shared', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('report.cuts.areas', fn ($areas): bool => collect($areas)->pluck('label')->all() === ['Producción', 'Almacén']));
});

it('names nobody: no company ID, no list of who was left out, no worker gross, no individual salaries', function (): void {
    Employee::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => null, 'hired_on' => null, 'employee_number' => 'E-SECRET']);
    SalaryOverride::factory()->create(['tenant_id' => $this->tenant->id, 'employee_number' => 'Producción-1', 'daily_salary_cents' => 91_234, 'effective_from' => '2026-01-01']);

    $response = $this->get(route('compensation.report.shared', $this->token))->assertOk();
    $props = $response->viewData('page')['props'];

    expect($props['report']['coverage']['missing'])->toBe([])
        ->and($props['report']['coverage']['missing_more'])->toBe(0)
        ->and($props['report']['coverage']['missing_without_number'])->toBe(0)
        ->and($props['report']['coverage']['total'])->toBe(29)->and($props['report']['coverage']['costed'])->toBe(28)
        ->and($props['report']['warnings']['uncosted'])->toBe([])
        ->and($props['report']['warnings']['individual']['count'])->toBe(0)
        ->and($props['report']['person_value']['rows'])->toBe([])
        ->and(json_encode($props))->not->toContain('E-SECRET')->not->toContain('Producción-1')->not->toContain('912.34')->not->toContain($this->grade->id)
        ->and(json_encode($props))->not->toContain($this->big->id)->not->toContain('/annex');
});

it('answers the same 404, naming no company, when the link is unknown, expired, revoked, of a blocked company or of a scenario that is gone', function (Closure $break): void {
    $break($this);

    $this->get(route('compensation.report.shared', $this->token))
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('compensation/shared-report')->where('report', null)->where('company', null)->where('expiresAt', null));

    expect(AuditEntry::query()->where('event', 'compensation.share_opened')->exists())->toBeFalse();
})->with([
    'expired, to the second' => function ($test): void {
        Carbon::setTestNow(now()->addDays(2));
    },
    'revoked' => function ($test): void {
        DB::table('compensation_report_shares')->update(['revoked_at' => now()]);
    },
    'unknown' => function ($test): void {
        $test->token = str_repeat('b', 64);
    },
    'blocked company' => function ($test): void {
        $test->tenant->update(['deactivated_at' => now()]);
    },
    'scenario gone' => function ($test): void {
        DB::table('compensation_report_shares')->delete();
        DB::table('scenarios')->delete();
        $test->share = null;
    },
]);

it('opens a link until its last second', function (): void {
    Carbon::setTestNow(now()->addDays(2)->subSecond());

    $this->get(route('compensation.report.shared', $this->token))->assertOk();
});

it('opens the link of a scenario that was archived after it was shared', function (): void {
    $this->scenario->update(['archived_at' => now()]);

    $this->get(route('compensation.report.shared', $this->token))->assertOk();
});

it('throttles a link at thirty openings a minute', function (): void {
    foreach (range(1, 30) as $ignored) {
        $this->get(route('compensation.report.shared', $this->token))->assertOk();
    }

    $this->get(route('compensation.report.shared', $this->token))->assertStatus(429);
    $this->get(route('compensation.report.shared', str_repeat('c', 64)))->assertNotFound();
});

it('never opens the link of another company from this company\'s tenant or the other way', function (): void {
    $other = Tenant::factory()->create(['trade_name' => 'Beta']);
    $foreign = Scenario::factory()->create(['tenant_id' => $other->id, 'name' => 'Secreta de Beta']);
    $token = str_repeat('d', 64);
    ReportShare::factory()->create(['scenario_id' => $foreign->id, 'tenant_id' => $other->id, 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => now()->addDay()]);

    $this->get(route('compensation.report.shared', $token))->assertOk()->assertInertia(fn (Assert $page) => $page->where('company', 'Beta')->where('report.scenario.name', 'Secreta de Beta'));
    $response = $this->get(route('compensation.report.shared', $this->token))->assertOk();

    expect($response->getContent())->not->toContain('Secreta de Beta');
});

it('shows what each category receives more a month only for the categories over the minimum, and no other scenario', function (): void {
    $few = JobGrade::factory()->create(['tenant_id' => $this->tenant->id, 'category' => 'Director', 'level' => 'A']);
    GradeSalary::factory()->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $few->id, 'daily_salary_cents' => 300_000, 'effective_from' => '2026-01-01']);
    Employee::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'job_grade_id' => $few->id, 'area_id' => $this->big->id, 'hired_on' => '2020-03-15', 'staff_group' => 'unionized', 'state' => 'JAL']);
    Scenario::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Otra propuesta']);

    $response = $this->get(route('compensation.report.shared', $this->token))->assertOk();
    $props = $response->viewData('page')['props']['report'];

    // Director (2) is under the minimum, and hiding it alone would leave it to the subtraction: Operador (28) goes with it.
    expect($props['raises'])->toBe([])
        ->and($props['others'])->toBe([])
        ->and(json_encode($props))->not->toContain('Director')->not->toContain('Otra propuesta');
});

it('shows the raise of a category over the minimum when nothing small is left out', function (): void {
    $this->get(route('compensation.report.shared', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('report.raises', fn ($raises): bool => collect($raises)->pluck('label')->all() === ['Operador · A']
            && collect($raises)->pluck('people')->all() === [28]));
});
