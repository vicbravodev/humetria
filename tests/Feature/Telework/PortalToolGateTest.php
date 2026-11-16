<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Telework\TeleworkerFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

mutates(RequirePortalTool::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    workIn($this->tenant);
    $this->token = resolve(IssuePortalLink::class)->handle($this->employee->id);
});

/** A company whose plan has only the tool named "attendance", which has no page of its own here. */
function onlyAttendance(): void
{
    resolve(CompanyModules::class)->syncPlan(test()->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));
}

it('answers 404 on the page by link of a tool the company does not have, and keeps the rest of the portal open', function (string $route, string $module): void {
    onlyAttendance();

    $this->get(route($route, $this->token))->assertNotFound();
    $this->get(route('portal.show', $this->token))->assertOk();
})->with([
    'Política NOM-035' => ['portal.policy.show', 'nom035'],
    'Apoyo NOM-035' => ['portal.support.show', 'nom035'],
]);

it('answers 404 on a page of the person in their session when the company of the address does not have the tool', function (string $route): void {
    TeleworkerFactory::new()->active()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id]);
    App\Modules\Leave\Models\LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $slug = asPerson($this->employee->id);

    $this->get(route($route, $slug))->assertOk();

    onlyAttendance();

    $this->get(route($route, $slug))->assertNotFound();
    $this->get(route('worker.home', $slug))->assertOk();
})->with(['portal.telework.show', 'portal.notices.show', 'portal.team.show', 'portal.vacations.show', 'portal.seating.show']);

it('lets a link that is not valid through to the page, which answers it its own way', function (): void {
    onlyAttendance();
    $request = Request::create('/portal/nope');
    $route = Route::getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);

    $response = resolve(RequirePortalTool::class)->handle($request, fn (): Illuminate\Http\Response => response('the page'), 'vacations');

    expect($response->getContent())->toBe('the page');
});
