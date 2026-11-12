<?php

declare(strict_types=1);

use App\Modules\Organization\Actions\CreateEmployee;
use App\Modules\Organization\Actions\DeactivateEmployee;
use App\Modules\Organization\Actions\ImportEmployees;
use App\Modules\Organization\Actions\ReactivateEmployee;
use App\Modules\Organization\Http\Controllers\EmployeeImportController;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantLimit;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Http\UploadedFile;

mutates(CreateEmployee::class, ReactivateEmployee::class, DeactivateEmployee::class, ImportEmployees::class, EmployeeImportController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
});

/** Gives the company a plan that covers `$seats` people, of whom `$used` are already on the roster. */
function planSeatsFor(Tenant $tenant, int $seats, int $used = 0): void
{
    resolve(CompanyLimits::class)->sync($tenant->id, ['seats' => $seats]);
    Employee::factory()->count($used)->create(['tenant_id' => $tenant->id, 'work_center_id' => WorkCenter::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('id')]);
    resolve(CompanyLimits::class)->reconcile();
}

function planSeatsUsed(Tenant $tenant): int
{
    return TenantLimit::query()->where('tenant_id', $tenant->id)->where('resource', 'seats')->firstOrFail()->used;
}

/** @return array<string, string> */
function planSeatForm(WorkCenter $workCenter, string $email = 'nuevo@acme.mx'): array
{
    return ['name' => 'Ana López', 'email' => $email, 'mobile' => '5512345678', 'gender' => 'female', 'work_center_id' => $workCenter->id, 'area' => 'Producción', 'job_title' => 'Operadora', 'employee_number' => 'N-'.$email];
}

/** @param  list<string>  $rows */
function planSeatCsv(array $rows): UploadedFile
{
    return UploadedFile::fake()->createWithContent('empleados.csv', implode("\n", ['nombre,correo,celular,genero,centro_de_trabajo,area,puesto,numero_de_empleado', ...$rows])."\n");
}

/** @return list<string> rows for `$count` new people. */
function planSeatRows(int $count, int $from = 1): array
{
    return array_map(fn (int $number): string => "Persona {$number},p{$number}@acme.mx,,Mujer,Planta Norte,Producción,Operadora,E-{$number}", range($from, $from + $count - 1));
}

describe('adding a person', function (): void {
    it('adds up to the contracted seats and stops at the next one with the reason and what to do', function (): void {
        planSeatsFor($this->tenant, 2, 1);

        $this->actingAs($this->owner)->post(route('tenant.employees.store', $this->tenant), planSeatForm($this->plant, 'a@acme.mx'))->assertSessionHasNoErrors();
        expect(planSeatsUsed($this->tenant))->toBe(2);

        $this->actingAs($this->owner)->post(route('tenant.employees.store', $this->tenant), planSeatForm($this->plant, 'b@acme.mx'))
            ->assertSessionHasErrors(['seats' => 'Tu plan cubre 2 personas y ya las tienes todas. Pide una ampliación a Humetria para dar de alta a más.']);

        expect(planSeatsUsed($this->tenant))->toBe(2)
            ->and(Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(2);
    });

    it('shows a channel company crossing a band what the next one costs: 50 to 51 people, $2,490 to $3,990', function (): void {
        [, $account] = paidCompany('consultant');
        $tenant = planFor($account, ['seats' => 50]);
        resolve(CompanyLimits::class)->sync($tenant->id, ['seats' => 50, 'centers' => 2]);
        $center = WorkCenter::factory()->for($tenant)->create();
        Employee::factory()->count(50)->create(['tenant_id' => $tenant->id, 'work_center_id' => $center->id]);
        resolve(CompanyLimits::class)->reconcile();

        $this->actingAs(ownerOf($tenant))->post(route('tenant.employees.store', $tenant), planSeatForm($center))
            ->assertSessionHasErrors(['seats' => 'Tu plan cubre 50 personas y ya las tienes todas. Pide una ampliación a Humetria para dar de alta a más. Con 51 personas el importe pasa de $2,490 a $3,990 al mes, sin IVA.']);
    });

    it('does not limit a company without a plan', function (): void {
        $this->actingAs($this->owner)->post(route('tenant.employees.store', $this->tenant), planSeatForm($this->plant))->assertSessionHasNoErrors();

        expect(Employee::query()->withoutGlobalScopes()->count())->toBe(1);
    });

    it('does not spend another company\'s seats', function (): void {
        planSeatsFor($this->tenant, 1, 1);
        $other = Tenant::factory()->create();
        $otherPlant = WorkCenter::factory()->for($other)->create();
        planSeatsFor($other, 5, 0);

        $this->actingAs(ownerOf($other))->post(route('tenant.employees.store', $other), planSeatForm($otherPlant))->assertSessionHasNoErrors();

        expect(planSeatsUsed($this->tenant))->toBe(1)->and(planSeatsUsed($other))->toBe(1);
    });
});

describe('deactivating and reactivating', function (): void {
    it('gives the seat back when someone is deactivated and takes it when they return, refusing when it is gone', function (): void {
        planSeatsFor($this->tenant, 2, 2);
        $first = Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->orderBy('id')->firstOrFail();

        $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $first->id]))->assertSessionHasNoErrors();
        expect(planSeatsUsed($this->tenant))->toBe(1);

        $this->actingAs($this->owner)->post(route('tenant.employees.store', $this->tenant), planSeatForm($this->plant, 'n@acme.mx'))->assertSessionHasNoErrors();
        expect(planSeatsUsed($this->tenant))->toBe(2);

        $this->actingAs($this->owner)->post(route('tenant.employees.reactivate', [$this->tenant, $first->id]))
            ->assertSessionHasErrors('seats');

        expect(Employee::query()->withoutGlobalScopes()->findOrFail($first->id)->deactivated_at)->not->toBeNull()
            ->and(planSeatsUsed($this->tenant))->toBe(2);
    });

    it('reactivates when the seat is free', function (): void {
        planSeatsFor($this->tenant, 2, 1);
        $person = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);

        $this->actingAs($this->owner)->post(route('tenant.employees.reactivate', [$this->tenant, $person->id]))->assertSessionHasNoErrors();

        expect(planSeatsUsed($this->tenant))->toBe(2)
            ->and(Employee::query()->withoutGlobalScopes()->findOrFail($person->id)->deactivated_at)->toBeNull();
    });

    it('lets a company above its limit read and deactivate, and only blocks adding until it is back under', function (): void {
        planSeatsFor($this->tenant, 3, 3);
        resolve(CompanyLimits::class)->sync($this->tenant->id, ['seats' => 2]);
        $first = Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->owner)->get(route('tenant.employees.index', $this->tenant))->assertOk();
        $this->actingAs($this->owner)->post(route('tenant.employees.store', $this->tenant), planSeatForm($this->plant))->assertSessionHasErrors('seats');
        $this->actingAs($this->owner)->post(route('tenant.employees.deactivate', [$this->tenant, $first->id]))->assertSessionHasNoErrors();
        $this->actingAs($this->owner)->post(route('tenant.employees.store', $this->tenant), planSeatForm($this->plant))->assertSessionHasErrors('seats');

        expect(planSeatsUsed($this->tenant))->toBe(2);
    });
});

describe('importing a file', function (): void {
    it('imports exactly the seats left', function (): void {
        planSeatsFor($this->tenant, 5, 2);

        $this->actingAs($this->owner)->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => planSeatCsv(planSeatRows(3))])->assertSessionHasNoErrors();

        expect(planSeatsUsed($this->tenant))->toBe(5)
            ->and(Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(5);
    });

    it('rejects the whole file when it adds one more than the seats left, saying how many are left', function (): void {
        planSeatsFor($this->tenant, 5, 2);

        $this->actingAs($this->owner)->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => planSeatCsv(planSeatRows(4))])
            ->assertSessionHasErrors(['file' => 'Tu plan cubre 5 personas y te quedan 3 lugares; esto agrega 4. No se agregó a nadie. Pide una ampliación a Humetria.']);

        expect(planSeatsUsed($this->tenant))->toBe(2)
            ->and(Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe(2);
    });

    it('warns on the preview too, before anything is confirmed', function (): void {
        planSeatsFor($this->tenant, 5, 4);

        $this->actingAs($this->owner)->post(route('tenant.employees.import.store', $this->tenant), ['file' => planSeatCsv(planSeatRows(2))])
            ->assertSessionHasErrors(['file' => 'Tu plan cubre 5 personas y te queda 1 lugar; esto agrega 2. No se agregó a nadie. Pide una ampliación a Humetria.']);
    });

    it('does not count the people a file only updates', function (): void {
        planSeatsFor($this->tenant, 3, 3);
        $existing = Employee::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->orderBy('id')->firstOrFail();
        $existing->update(['email' => 'ana@acme.mx', 'employee_number' => 'E-9']);

        $this->actingAs($this->owner)->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => planSeatCsv(['Ana Nueva,ana@acme.mx,,Mujer,Planta Norte,Producción,Operadora,E-9'])])
            ->assertSessionHasNoErrors();

        expect(planSeatsUsed($this->tenant))->toBe(3);
    });

    it('imports any size when the company has no plan', function (): void {
        $this->actingAs($this->owner)->post(route('tenant.employees.import.store', $this->tenant), ['confirm' => '1', 'file' => planSeatCsv(planSeatRows(6))])->assertSessionHasNoErrors();

        expect(Employee::query()->withoutGlobalScopes()->count())->toBe(6);
    });
});

describe('shown on the employees page', function (): void {
    it('tells the company how many of the seats of its plan it uses', function (): void {
        planSeatsFor($this->tenant, 50, 42);

        $this->actingAs($this->owner)->get(route('tenant.employees.index', $this->tenant))->assertOk()->assertInertia(fn ($page) => $page
            ->where('plan_limit', ['used' => 42, 'amount' => 50, 'remaining' => 8, 'over' => false]));
    });

    it('has no limit to show a company without a plan', function (): void {
        $this->actingAs($this->owner)->get(route('tenant.employees.index', $this->tenant))->assertOk()->assertInertia(fn ($page) => $page->where('plan_limit', null));
    });
});
