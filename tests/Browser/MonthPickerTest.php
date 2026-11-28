<?php

declare(strict_types=1);

use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Tenancy\Models\Tenant;

/** The superadmin on an account that pays "Cliente Nuevo" (100 people since November 2026), and its address. */
function monthAccount(): string
{
    signInAsSuperadmin();
    $account = BillingAccount::factory()->create();
    $tenant = Tenant::factory()->create(['trade_name' => 'Cliente Nuevo']);
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $tenant->id]);
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'starts_on' => '2026-11-01', 'renews_on' => '2026-12-01']);

    return "/admin/billing/accounts/{$account->id}";
}

/** The value the form sends for a field. */
function sentMonth(): string
{
    return "() => document.querySelector('input[type=hidden][name=period]').value";
}

it('takes a month typed in digits, writes it as mm/aaaa, previews it and generates its draft', function (): void {
    visit(monthAccount())
        ->click('Generar estado')
        ->assertAttribute('#period', 'type', 'text')
        ->fill('#period', '')
        ->type('#period', '122026')
        ->assertValue('#period', '12/2026')
        ->assertScript(sentMonth(), '2026-12')
        ->assertSee('100 personas × $49 = $4,900')
        ->press('Generar borrador')
        ->assertSee('Cliente Nuevo · dic 2026')
        ->assertNoJavaScriptErrors();

    expect(Statement::query()->sole()->period_start->toDateString())->toBe('2026-12-01');
});

it('picks a month from the grid of the year, with the keyboard, and gives the focus back to the field', function (): void {
    visit(monthAccount())
        ->click('Generar estado')
        ->fill('#period', '11/2026')
        ->click('[data-slot="month-picker"] button:has-text("Abrir los meses")')
        ->assertVisible('[data-slot="popover-content"] [data-slot="month-grid"]')
        ->assertScript('() => document.activeElement.dataset.pick', '2026-11')
        ->keys('[data-pick][tabindex="0"]', ['ArrowRight', 'Enter'])
        ->assertMissing('[data-slot="popover-content"]')
        ->assertValue('#period', '12/2026')
        ->assertScript(sentMonth(), '2026-12')
        ->assertScript('() => document.activeElement.id', 'period')
        ->click('#period')
        ->click('[data-slot="popover-content"] button:has-text("Año siguiente")')
        ->click('[data-slot="popover-content"] button:has-text("Ene")')
        ->assertValue('#period', '01/2027');
});

it('says when the text is no month, and closes the grid with Escape', function (): void {
    visit(monthAccount())
        ->click('Generar estado')
        ->fill('#period', '13/2026')
        ->keys('#period', 'Tab')
        ->assertSee('No es un mes: escríbelo como 10/2026.')
        ->click('#period')
        ->assertVisible('[data-slot="month-grid"]')
        ->keys('#period', 'Escape')
        ->assertMissing('[data-slot="popover-content"]')
        ->assertVisible('[role="dialog"]');
});

it('opens the months as a sheet from the bottom on a phone', function (bool $dark): void {
    visitPage('billing/account', monthAccount(), $dark, true)
        ->click('Generar estado')
        ->click('#period')
        ->assertVisible('[data-slot="sheet-content"] [data-slot="month-grid"]')
        ->assertScript('() => [...document.querySelectorAll("[data-slot=sheet-content] [data-pick]")].every((month) => month.getBoundingClientRect().height >= 44)', true)
        ->click('[data-slot="sheet-content"] button:has-text("Dic")')
        ->assertMissing('[data-slot="sheet-content"] [data-slot="month-grid"]')
        ->assertValue('#period', '12/2026')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertNoJavaScriptErrors();
})->with(['light' => false, 'dark' => true]);
