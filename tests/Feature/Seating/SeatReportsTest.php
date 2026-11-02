<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Actions\ReportSeat;
use App\Modules\Seating\Actions\ResolveSeatReport;
use App\Modules\Seating\Http\Controllers\SeatReportController;
use App\Modules\Seating\Models\PositionAssignment;
use App\Modules\Seating\Models\SeatReport;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ReportSeat::class, ResolveSeatReport::class, SeatReportController::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 12:00:00');
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    PositionAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'position_id' => $this->position->id]);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $this->cora->id]);
});

it('takes the report of a damaged seat from the portal, tied to the center and the position and to nobody', function (): void {
    Log::spy();

    $this->post(route('portal.seating.reports.store', $this->token), ['description' => 'Se le cayó el respaldo a la silla de la caja 2.'])
        ->assertRedirect(route('portal.seating.show', $this->token))->assertSessionHasNoErrors();

    $report = SeatReport::query()->sole();
    expect($report->work_center_id)->toBe($this->center->id)
        ->and($report->position_id)->toBe($this->position->id)
        ->and($report->status->value)->toBe('open')
        ->and($report->reported_via_portal)->toBeTrue()
        ->and($report->resolution)->toBeNull()
        ->and(array_keys($report->getAttributes()))->not->toContain('employee_id');
    Log::shouldHaveReceived('info')->with('seating.seat_reported', ['report_id' => $report->id, 'work_center_id' => $this->center->id])->once();
    expect(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain('respaldo');
});

it('rejects an empty or too long description and a link that is not valid', function (): void {
    $this->post(route('portal.seating.reports.store', $this->token), ['description' => ' '])->assertSessionHasErrors('description');
    $this->post(route('portal.seating.reports.store', $this->token), ['description' => str_repeat('a', 1001)])->assertSessionHasErrors('description');
    $this->post(route('portal.seating.reports.store', 'nope'), ['description' => 'Algo'])->assertNotFound();

    expect(SeatReport::query()->count())->toBe(0);
});

it('lets the committee and RH see the open reports of the center', function (TenantRole $role): void {
    SeatReport::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'position_id' => $this->position->id, 'description' => 'Silla rota']);
    SeatReport::factory()->resolved()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'description' => 'Ya arreglada']);
    $user = $role === TenantRole::Committee ? $this->cora : memberOf($this->tenant, $role);

    $this->actingAs($user)->get(route('tenant.seating.reports.index', [$this->tenant, $this->center->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('seating/reports')->where('reports.total', 1)->where('reports.data.0.description', 'Silla rota')->where('reports.data.0.positionName', 'Cajera')
            ->where('status', 'open')->where('openCount', 1)->where('resolvedCount', 1));
    $this->get(route('tenant.seating.reports.index', [$this->tenant, $this->center->id, 'status' => 'resolved']))
        ->assertInertia(fn (Assert $page) => $page->where('reports.total', 1)->where('reports.data.0.description', 'Ya arreglada'));
})->with([TenantRole::Owner, TenantRole::Hr, TenantRole::Committee]);

it('refuses the reports to the viewer and hides other centers from the committee', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.seating.reports.index', [$this->tenant, $this->center->id]))->assertForbidden();
    $this->actingAs($this->cora)->get(route('tenant.seating.reports.index', [$this->tenant, $other->id]))->assertNotFound();
});

it('paginates the reports on the server', function (): void {
    SeatReport::factory()->count(17)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    $this->actingAs($this->hr)->get(route('tenant.seating.reports.index', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->where('reports.total', 17)->where('reports.per_page', 15)->has('reports.data', 15));
});

it('resolves a report with the resolution RH writes, audited and logged with ids only', function (): void {
    Log::spy();
    $report = SeatReport::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);

    $this->actingAs($this->hr)->post(route('tenant.seating.reports.resolve', [$this->tenant, $report->id]), ['resolution' => 'Se cambió el respaldo.'])->assertRedirect()->assertSessionHasNoErrors();

    $fresh = SeatReport::query()->sole();
    expect($fresh->status->value)->toBe('resolved')->and($fresh->resolution)->toBe('Se cambió el respaldo.')->and($fresh->resolved_at?->toDateTimeString())->toBe('2026-03-01 12:00:00');
    $record = ['report_id' => $report->id, 'work_center_id' => $this->center->id];
    expect(AuditEntry::query()->where('event', 'seating.seat_report_resolved')->sole()->metadata)->toEqual($record);
    Log::shouldHaveReceived('info')->with('seating.seat_report_resolved', $record)->once();
});

it('needs a resolution, resolves only once, and only for manage-seating', function (): void {
    $report = SeatReport::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $url = route('tenant.seating.reports.resolve', [$this->tenant, $report->id]);

    $this->actingAs($this->cora)->post($url, ['resolution' => 'x'])->assertForbidden();
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post($url, ['resolution' => 'x'])->assertForbidden();
    $this->actingAs($this->hr)->post($url, ['resolution' => '  '])->assertSessionHasErrors('resolution');
    $this->post($url, ['resolution' => 'Listo']);
    $this->post($url, ['resolution' => 'Otra vez'])->assertSessionHasErrors('status');

    expect(SeatReport::query()->sole()->resolution)->toBe('Listo');
});

it('does not touch the reports of another company', function (): void {
    $foreign = SeatReport::factory()->create();

    $this->actingAs($this->hr)->post(route('tenant.seating.reports.resolve', [$this->tenant, $foreign->id]), ['resolution' => 'x'])->assertNotFound();
    $this->get(route('tenant.seating.reports.index', [$this->tenant, $foreign->work_center_id]))->assertNotFound();
});

it('has no destroy route for reports', function (): void {
    $deletes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => in_array('DELETE', $route->methods(), true) && str_contains((string) $route->getName(), 'seating.reports'));

    expect($deletes)->toHaveCount(0);
});
