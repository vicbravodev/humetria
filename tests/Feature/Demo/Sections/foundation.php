<?php

declare(strict_types=1);

use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Enums\Module;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Support\DemoMail;
use Database\Seeders\Demo\DemoPerson;
use Database\Seeders\Demo\DemoRoster;

/** The demo company's people, by the name of their work center. */
function demoHeadcount(string $tenantId): array
{
    $names = WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('name', 'id');
    $counts = Employee::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->get()
        ->countBy(fn (Employee $employee): string => (string) $names[$employee->work_center_id]);

    return $counts->sortKeys()->all();
}

/*
 * The foundation's groups: DemoStoriesTest includes this file inside its one test, on the prepared demo, so `$this` is
 * that test.
 */
return [
    'builds exactly 180 people in the four centers' => function (): void {
        $people = DemoRoster::people();
        $byCenter = array_count_values(array_map(fn (DemoPerson $person): string => $person->centerKey, $people));

        expect($people)->toHaveCount(180)
            ->and($byCenter)->toEqual(['monterrey' => 15, 'offices' => 30, 'distribution' => 51, 'plant' => 84]);

        $tenantId = (string) resolve(DemoCompanies::class)->id();

        expect(demoHeadcount($tenantId))->toBe([
            'Centro de distribución' => 51,
            'Oficinas Querétaro' => 30,
            'Planta Querétaro' => 84,
            'Sucursal Monterrey' => 15,
        ]);
    },

    'draws the reporting lines from one director down, with a level, area and position for everyone' => function (): void {
        $people = DemoRoster::people();
        $keys = array_map(fn (DemoPerson $person): string => $person->key, $people);
        $tops = array_filter($people, fn (DemoPerson $person): bool => $person->reportsTo === null);

        expect($tops)->toHaveCount(1)
            ->and(array_unique($keys))->toHaveCount(180);

        // A manager comes before the people under them, so each one can be created pointing at someone who exists.
        $seen = [];

        foreach ($people as $person) {
            expect($person->reportsTo === null || isset($seen[$person->reportsTo]))->toBeTrue()
                ->and($person->area)->not->toBe('')
                ->and($person->position)->not->toBe('')
                ->and($person->level)->not->toBe('');
            $seen[$person->key] = true;
        }

        $employees = Employee::query()->withoutGlobalScopes()->where('tenant_id', resolve(DemoCompanies::class)->id())->get()->keyBy('id');

        foreach ($people as $person) {
            $employee = $employees[DemoRoster::employeeId($person->key)];
            expect($employee->manager_id)->toBe($person->reportsTo === null ? null : DemoRoster::employeeId($person->reportsTo))
                ->and($employee->name)->toBe($person->name)
                ->and($employee->job_title)->toBe($person->position)
                ->and($employee->job_grade_id)->not->toBeNull()
                ->and($employee->hired_on)->not->toBeNull();
        }
    },

    'gives every person the same id on every run' => function (): void {
        $ids = fn (): array => array_map(fn (DemoPerson $person): string => DemoRoster::employeeId($person->key), DemoRoster::people());
        $first = $ids();

        expect($ids())->toBe($first)
            ->and(array_unique($first))->toHaveCount(180)
            ->and($first)->each->toBeUuid()
            ->and(DemoRoster::employeeId('plant-001'))->toBe(DemoRoster::employeeId('plant-001'))
            ->and(DemoRoster::employeeId('plant-001'))->not->toBe(DemoRoster::employeeId('plant-002'));

        $stored = Employee::query()->withoutGlobalScopes()->where('tenant_id', resolve(DemoCompanies::class)->id())->pluck('id')->sort()->values()->all();

        expect($stored)->toBe(collect($first)->sort()->values()->all());
    },

    'uses only demo addresses' => function (): void {
        $emails = array_map(fn (DemoPerson $person): string => $person->email, DemoRoster::people());

        expect(array_unique($emails))->toHaveCount(180);

        foreach ($emails as $email) {
            expect($email)->toMatch('/^[a-z0-9.]+@'.preg_quote(DemoMail::DOMAIN, '/').'$/');
        }

        $tenantId = resolve(DemoCompanies::class)->id();
        $stored = Employee::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->pluck('email')->all();
        $members = array_map(fn ($member): string => $member->email, resolve(Members::class)->all());

        expect($members)->not->toBeEmpty();

        foreach ([...$stored, ...$members] as $email) {
            expect($email)->toEndWith('@'.DemoMail::DOMAIN);
        }
    },

    'creates the company flagged as demo, active and with every module' => function (): void {
        $tenant = Tenant::query()->findOrFail(resolve(DemoCompanies::class)->id());
        $members = collect(resolve(Members::class)->all());
        $everyModule = array_values(array_map(fn (Module $module): string => $module->value, array_filter(Module::cases(), fn (Module $module): bool => $module->keptWithoutPlan())));

        expect($tenant->is_demo)->toBeTrue()
            ->and($tenant->legal_name)->toBe('Manufacturas Alba del Bajío, S.A. de C.V.')
            ->and($tenant->status())->toBe(TenantStatus::Active)
            ->and(resolve(CompanyModules::class)->enabledKeys($tenant->id))->toBe($everyModule)
            ->and(resolve(CompanyLimits::class)->usage($tenant->id))->toBe([])
            ->and($members->where('role', TenantRole::Owner)->pluck('email')->all())->toBe([DemoRoster::person(DemoRoster::OWNER)->email])
            ->and($members->where('role', TenantRole::Hr)->pluck('email')->all())->toBe([DemoRoster::person(DemoRoster::HR)->email]);
    },
];
