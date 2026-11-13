<?php

declare(strict_types=1);

use App\Http\Middleware\RequirePortalTool;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

mutates(RequirePortalTool::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->employee = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Ruiz']);
    workIn($this->tenant);
    $this->token = resolve(IssuePortalLink::class)->handle($this->employee->id);
});

it('answers 404 on the page of a tool the company does not have, and keeps the rest of the portal open', function (string $route, string $module): void {
    // A company whose plan has only the tool named "attendance", which has no page in the portal.
    resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['attendance'], CarbonImmutable::now(config('app.display_timezone')));

    $this->get(route($route, $this->token))->assertNotFound();
    $this->get(route('portal.show', $this->token))->assertOk();
})->with([
    'Vacaciones' => ['portal.vacations.show', 'vacations'],
    'Ley Silla' => ['portal.seating.show', 'seating'],
    'Organigrama' => ['portal.team.show', 'org_chart'],
    'Política NOM-035' => ['portal.policy.show', 'nom035'],
    'Apoyo NOM-035' => ['portal.support.show', 'nom035'],
    'NOM-037' => ['portal.notices.show', 'nom037'],
]);

it('lets a link that is not valid through to the page, which answers it its own way', function (): void {
    $this->get(route('portal.checklist.show', ['nope', (string) Illuminate\Support\Str::uuid()]))->assertOk()->assertInertia(fn ($page) => $page->where('unavailable', true));
});
