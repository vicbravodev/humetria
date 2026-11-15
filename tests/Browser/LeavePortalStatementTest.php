<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * The statement of Ana's second year, issued on her anniversary and not yet confirmed.
 *
 * @return array{statement: LeaveStatement, url: string}
 */
function leaveStatementPortal(): array
{
    test()->travelTo('2026-03-16 18:00:00');
    $tenant = Tenant::factory()->create(['slug' => 'acme', 'trade_name' => 'Autopartes Demo']);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $ana = Employee::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    $period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14, 'premium_percent' => 25]);
    $statement = LeaveStatement::factory()->create(['tenant_id' => $tenant->id, 'leave_entitlement_id' => $period->id, 'issued_at' => '2026-03-15 18:00:00']);
    workIn($tenant);

    return ['statement' => $statement, 'url' => '/portal/'.resolve(IssuePortalLink::class)->handle($ana->id).'/vacations/statements/'.$statement->id];
}

it('shows the statement and lets the person confirm they received it', function (bool $dark, bool $mobile): void {
    $portal = leaveStatementPortal();

    visitPage('leave/portal-statement', $portal['url'], $dark, $mobile)
        ->assertSee('Constancia de vacaciones')
        ->assertSee('Ana Solís')
        ->assertSee('2 años')
        ->assertSee('14 días')
        ->assertSee('25 % sobre los días de vacaciones')
        ->assertSee('15 de septiembre de 2026')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->press('Confirmo que recibí esta constancia')
        ->assertSee('Recibida el 16 de marzo de 2026')
        ->assertDontSee('Confirmo que recibí esta constancia')
        ->assertNoJavaScriptErrors();

    expect($portal['statement']->refresh()->acknowledged_at)->not->toBeNull();
})->with($looks);

it('offers the statement on the vacations page while it is not confirmed', function (): void {
    $portal = leaveStatementPortal();

    visitPage('leave/portal', dirname($portal['url'], 2))
        ->assertSee('Constancia de vacaciones 2026')
        ->click('Constancia de vacaciones 2026')
        ->assertSee('Constancia de vacaciones')
        ->assertSee('Ana Solís');
});
