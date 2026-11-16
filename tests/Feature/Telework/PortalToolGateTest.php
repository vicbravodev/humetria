<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;

mutates(RequirePortalTool::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    workIn($this->tenant);
});

/** A company whose plan has only the tool named "attendance", which has no page of its own here. */
function onlyAttendance(): void
{
    resolve(CompanyModules::class)->syncPlan(test()->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
}

it('answers 404 on a page of the person in their session when the company of the address does not have the tool', function (string $route): void {
    TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    App\Modules\Leave\Models\LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $slug = asPerson($this->employee->id);

    $this->get(route($route, $slug))->assertOk();

    onlyAttendance();

    $this->get(route($route, $slug))->assertNotFound();
    $this->get(route('worker.home', $slug))->assertOk();
})->with(['portal.telework.show', 'portal.notices.show', 'portal.team.show', 'portal.vacations.show', 'portal.seating.show', 'portal.policy.show']);
