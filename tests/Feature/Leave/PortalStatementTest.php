<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Support\StatementSheet;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(StatementSheet::class);

beforeEach(function (): void {
    workIn($this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']));
    // 18:00 UTC is noon in Mexico City.
    $this->travelTo('2026-03-16 18:00:00');
    LeaveSetting::factory()->create(['tenant_id' => $this->tenant->id, 'tracking_since' => '2025-01-01']);
    $this->ana = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    $this->period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14, 'extra_days' => 2, 'premium_percent' => 30]);
    $this->statement = LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'leave_entitlement_id' => $this->period->id, 'issued_at' => '2026-03-15 18:00:00']);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
});

it('lives under the personal link, throttled as the rest of the portal', function (): void {
    expect(route('portal.vacations.statements.show', ['abc', 's1'], absolute: false))->toBe('/portal/abc/vacations/statements/s1')
        ->and(route('portal.vacations.statements.acknowledge', ['abc', 's1'], absolute: false))->toBe('/portal/abc/vacations/statements/s1/acknowledge')
        ->and(Route::getRoutes()->getByName('portal.vacations.statements.show')?->gatherMiddleware())->toContain('throttle:portal-views')
        ->and(Route::getRoutes()->getByName('portal.vacations.statements.acknowledge')?->gatherMiddleware())->toContain('throttle:portal-actions');
});

it('shows the sheet: seniority, days, when to enjoy them and the premium as a percent, never an amount', function (): void {
    $this->get(route('portal.vacations.statements.show', [$this->token, $this->statement->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('leave/portal-statement')
            ->where('token', $this->token)
            ->where('company', 'Acme')
            ->where('statement.id', $this->statement->id)
            ->where('sheet', [
                'company' => 'Acme', 'name' => 'Ana Solís', 'startedOn' => '15 de marzo de 2024', 'years' => 2, 'days' => 16,
                'enjoyFrom' => '15 de marzo de 2026', 'enjoyBy' => '15 de septiembre de 2026', 'premiumPercent' => 30,
                'issuedOn' => '15 de marzo de 2026', 'acknowledgedOn' => null,
            ]));
});

it('turns the sheet into a received one once the person confirms, only once', function (): void {
    $this->post(route('portal.vacations.statements.acknowledge', [$this->token, $this->statement->id]))
        ->assertRedirect(route('portal.vacations.statements.show', [$this->token, $this->statement->id]));

    $this->get(route('portal.vacations.statements.show', [$this->token, $this->statement->id]))
        ->assertInertia(fn (Assert $page) => $page->where('sheet.acknowledgedOn', '16 de marzo de 2026'));

    $this->post(route('portal.vacations.statements.acknowledge', [$this->token, $this->statement->id]))
        ->assertSessionHasErrors(['statement' => 'Ya confirmó esta constancia.']);
});

it("answers 'not found' for the statement of somebody else, for any link", function (): void {
    $caro = Employee::factory()->create(['tenant_id' => $this->tenant->id]);
    $other = LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $caro->id, 'leave_entitlement_id' => LeaveEntitlement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $caro->id])->id]);

    $this->get(route('portal.vacations.statements.show', [$this->token, $other->id]))->assertNotFound();
    $this->post(route('portal.vacations.statements.acknowledge', [$this->token, $other->id]))->assertNotFound();
    $this->get(route('portal.vacations.statements.show', ['made-up', $this->statement->id]))->assertNotFound();
    expect($other->refresh()->acknowledged_at)->toBeNull();
});

it('lists on the vacations page only the statements still to confirm', function (): void {
    $older = LeaveEntitlement::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'years_of_service' => 1, 'anniversary_on' => '2025-03-15']);
    LeaveStatement::factory()->create(['tenant_id' => $this->tenant->id, 'leave_entitlement_id' => $older->id, 'acknowledged_at' => '2025-03-20 18:00:00']);

    $this->get(route('portal.vacations.show', $this->token))
        ->assertInertia(fn (Assert $page) => $page->where('statements', [['id' => $this->statement->id, 'year' => 2026]]));
});
