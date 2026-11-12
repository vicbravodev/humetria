<?php

declare(strict_types=1);

use App\Modules\Leave\Models\LeaveEntitlement;
use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveStatement;
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
 * The statement of Ana's second year, as RH opens it from her record.
 *
 * @return array{url: string, statement: LeaveStatement}
 */
function leaveStatementPanel(bool $acknowledged): array
{
    test()->travelTo('2026-03-16 18:00:00');
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->firstOrFail();
    workIn($tenant);
    LeaveSetting::factory()->create(['tenant_id' => $tenant->id, 'tracking_since' => '2025-01-01']);
    $ana = EmployeeFactory::new()->create(['tenant_id' => $tenant->id, 'name' => 'Ana Solís', 'hired_on' => '2024-03-15']);
    $period = LeaveEntitlement::factory()->withGrant()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'years_of_service' => 2, 'anniversary_on' => '2026-03-15', 'statutory_days' => 14, 'premium_percent' => 25]);
    $statement = LeaveStatement::factory()->create(['tenant_id' => $tenant->id, 'employee_id' => $ana->id, 'leave_entitlement_id' => $period->id, 'issued_at' => '2026-03-15 18:00:00', 'acknowledged_at' => $acknowledged ? '2026-03-16 18:00:00' : null]);

    return ['url' => "{$url}/vacations/statements/{$statement->id}", 'statement' => $statement];
}

it('shows RH the sheet with its acknowledgment', function (bool $dark, bool $mobile): void {
    visitPage('leave/statement', leaveStatementPanel(acknowledged: true)['url'], $dark, $mobile)
        ->assertSee('Constancia de vacaciones')
        ->assertSee('Ana Solís')
        ->assertSee('14 días')
        ->assertSee('25 % sobre los días de vacaciones')
        ->assertSee('Recibida por la persona el 16 de marzo de 2026')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
})->with($looks);

it('says there is no acknowledgment yet', function (): void {
    visitPage('leave/statement', leaveStatementPanel(acknowledged: false)['url'])
        ->assertSee('Sin acuse')
        ->assertDontSee('Recibida por la persona')
        ->assertNoJavaScriptErrors();
});

it('prints the sheet alone, without the frame or the buttons', function (): void {
    $print = <<<'JS'
() => {
    const rules = (list) => [...list].flatMap((rule) => rule.cssRules ? [rule, ...rules(rule.cssRules)] : [rule]);
    const css = [...document.styleSheets].flatMap((sheet) => rules(sheet.cssRules)).filter((rule) => rule.parentRule?.media?.mediaText === 'print').map((rule) => rule.cssText).join(' ');

    return css.includes('.print\\:hidden') && /\[data-slot="?sidebar"?\]/.test(css);
}
JS;

    visitPage('leave/statement', leaveStatementPanel(acknowledged: true)['url'])
        ->assertScript($print, true);
});

it('is reached from the record of the person', function (): void {
    $panel = leaveStatementPanel(acknowledged: false);

    visitPage('leave/employee', dirname($panel['url'], 2).'/employees/'.$panel['statement']->employee_id)
        ->click('[role=tab]:has-text("Constancias")')
        ->click('Constancia del periodo 2026')
        ->assertSee('Sin acuse')
        ->assertSee('Volver al expediente')
        ->assertNoJavaScriptErrors();
});
