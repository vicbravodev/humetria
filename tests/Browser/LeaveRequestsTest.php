<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;

// Light and dark, on a desktop and on a phone.
$looks = [
    'light' => [false, false],
    'dark' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
];

/**
 * RH's panel on Wednesday 4 November 2026 at noon in Mexico City: Ana's request reached RH because her manager did not answer,
 * Caro's still waits for her manager.
 *
 * @return array{url: string, tenant: Tenant}
 */
function leaveRequestsPanel(): array
{
    test()->travelTo('2026-11-04 18:00:00');
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $boss = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Beto Ruiz']);
    $ana = EmployeeFactory::new()->reportingTo($boss)->create(['name' => 'Ana Solís']);
    $caro = EmployeeFactory::new()->reportingTo($boss)->create(['name' => 'Caro Luz']);
    LeaveRequest::factory()->escalated()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'approver_employee_id' => $boss->id]);
    LeaveRequest::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $caro->id, 'approver_employee_id' => $boss->id, 'starts_on' => '2026-12-07', 'ends_on' => '2026-12-08', 'working_days' => 2]);

    return ['url' => "{$url}/vacations/requests", 'tenant' => $tenant];
}

it('shows RH what reached them first and lets them see the rest by tab', function (bool $dark, bool $mobile): void {
    $queue = leaveRequestsPanel();

    visitPage('leave/requests', $queue['url'], $dark, $mobile)
        ->assertSee('Solicitudes')
        ->assertSee('Ana Solís')
        ->assertSee('Su jefe no respondió en 3 días hábiles')
        ->assertDontSee('Caro Luz')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]')
        ->click('nav[aria-label="Estado de las solicitudes"] a:has-text("En espera del jefe")')
        ->assertSee('Caro Luz')
        ->assertDontSee('Ana Solís')
        ->assertNoJavaScriptErrors();
})->with($looks);

it('says so when a tab has nothing', function (): void {
    $url = signInToPanel();
    workIn(Tenant::query()->where('slug', 'acme')->firstOrFail());

    visitPage('leave/requests', "{$url}/vacations/requests")
        ->assertSee('Nada por atender.')
        ->assertNoJavaScriptErrors();
});

it('searches the queue by name', function (): void {
    $queue = leaveRequestsPanel();

    visitPage('leave/requests', "{$queue['url']}?tab=waiting")
        ->type('input[type=search]', 'zzz')
        ->assertSee('Ninguna solicitud espera a un jefe.')
        ->assertDontSee('Caro Luz');
});
