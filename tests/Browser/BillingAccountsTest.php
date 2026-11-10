<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('lists who pays what, with the kind of each account and how many companies it pays', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $consultant = BillingAccount::factory()->consultant()->create(['legal_name' => 'Consultores Asociados S.C.', 'rfc' => 'CAS010101AB1', 'contact_name' => 'Laura Pérez', 'contact_email' => 'laura@consultores.mx']);
    AccountCompany::factory()->count(2)->for($consultant, 'account')->create();
    BillingAccount::factory()->create(['legal_name' => 'Grupo Directo S.A.']);

    visitPage('billing/accounts', '/admin/billing/accounts', $dark, $mobile)
        ->assertSee('Cuentas')
        ->assertSee('2 cuentas')
        ->assertSee('Consultores Asociados S.C.')
        ->assertSee('CAS010101AB1')
        ->assertSee('Consultor')
        ->assertSee('2 empresas')
        ->assertSee('Laura Pérez')
        ->assertSee('Grupo Directo S.A.')
        ->assertSee('Crear cuenta');
})->with($looks);

it('shows an empty list with its next step', function (): void {
    signInAsSuperadmin();

    visitPage('billing/accounts', '/admin/billing/accounts')
        ->assertSee('Aún no hay cuentas')
        ->assertSee('Crea la cuenta de quien paga');
});

it('shows support the accounts without the button that creates one', function (): void {
    $this->actingAs(support());
    BillingAccount::factory()->create(['legal_name' => 'Grupo Directo S.A.']);

    visitPage('billing/accounts', '/admin/billing/accounts')
        ->assertSee('Grupo Directo S.A.')
        ->assertDontSee('Crear cuenta');
});

it('creates an account from the list and lands on its page', function (): void {
    signInAsSuperadmin();

    visitPage('billing/accounts', '/admin/billing/accounts')
        ->click('Crear cuenta')
        ->assertSee('Quien paga por una o varias empresas')
        ->click('Consultor')
        ->fill('legal_name', 'Consultores Nuevos S.C.')
        ->fill('rfc', 'CNU010101AB1')
        ->fill('contact_name', 'Marta Ruiz')
        ->fill('contact_email', 'marta@nuevos.mx')
        ->press('Guardar cuenta')
        ->assertSee('Cuenta creada.')
        ->assertSee('Consultores Nuevos S.C.');

    expect(BillingAccount::query()->sole()->kind->value)->toBe('consultant');
});

it('shows an account with the companies it pays, none of them with a plan yet', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->consultant()->create(['legal_name' => 'Consultores Asociados S.C.', 'rfc' => 'CAS010101AB1', 'contact_name' => 'Laura Pérez', 'contact_email' => 'laura@consultores.mx']);
    $acme = Tenant::factory()->create(['trade_name' => 'Acme Industrial', 'rfc' => 'ACM010101AB1']);
    WorkCenter::factory()->for($acme)->count(2)->create();
    AccountCompany::factory()->for($account, 'account')->create(['tenant_id' => $acme->id, 'starts_on' => '2026-12-01']);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}", $dark, $mobile)
        ->assertSee('Consultores Asociados S.C.')
        ->assertSee('Consultor')
        ->assertSee('CAS010101AB1')
        ->assertSee('Editar cuenta')
        ->assertSee('Vincular empresa')
        ->assertSee('Acme Industrial')
        ->assertSee('Sin plan asignado')
        ->assertSee('Asignar plan');
})->with($looks);

it('shows an account that pays nothing yet with its next step', function (): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->create();

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->assertSee('Esta cuenta aún no paga ninguna empresa')
        ->assertSee('Vincula una empresa');
});

it('links a company found by name, and the account then pays it', function (): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->create();
    Tenant::factory()->create(['trade_name' => 'Zapatería Libre', 'rfc' => 'ZAP010101AB1']);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->click('Vincular empresa')
        ->click('#billing-company_id')
        ->type('[role="combobox"][aria-controls="billing-company_id-options"]', 'zapat')
        ->assertSee('Zapatería Libre · ZAP010101AB1')
        ->keys('[role="combobox"][aria-controls="billing-company_id-options"]', 'Enter')
        ->press('Vincular')
        ->assertSee('Empresa vinculada.')
        ->assertSee('Zapatería Libre')
        ->assertSee('Sin plan asignado');

    expect(AccountCompany::query()->sole()->billing_account_id)->toBe($account->id);
});

it('shows an account with its fiscal data and the way to correct it', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->consultant()->create(['legal_name' => 'Consultores Asociados S.C.', 'rfc' => 'CAS010101AB1', 'contact_name' => 'Laura Pérez', 'contact_email' => 'laura@consultores.mx']);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}", $dark, $mobile)
        ->assertSee('Consultores Asociados S.C.')
        ->assertSee('Consultor')
        ->assertSee('CAS010101AB1')
        ->assertSee('Editar cuenta');
})->with($looks);

it('shows a consultant\'s portfolio: each company with its plan and cost, and what the account adds up to', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->consultant()->create(['legal_name' => 'Consultores Asociados S.C.']);
    foreach (range(1, 5) as $number) {
        planFor($account, [], Tenant::factory()->create(['trade_name' => "Cliente {$number}"]));
    }

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}", $dark, $mobile)
        ->assertSee('Al mes, sin IVA')
        ->assertSee('$19,950')
        ->assertSee('$23,142')
        ->assertSee('Próxima renovación')
        ->assertSee('Cliente 1')
        ->assertSee('Cumplimiento')
        ->assertSee('Canal Cumplimiento')
        ->assertSee('$3,990')
        ->assertSee('0 de 100 personas')
        ->assertSee('Editar plan');
})->with($looks);

it('says why the channel discount applies, or what is missing for it', function (): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->consultant()->create();
    foreach (range(1, 2) as $ignored) {
        planFor($account, ['plan' => Plan::Integral, 'profile' => Profile::ChannelIntegral, 'centers' => 3]);
    }

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->assertSee('Sin descuento: 2 de las 3 empresas con Integral pagado')
        ->assertSee('$19,800');
});

it('shows a company that needs a quotation without an amount', function (): void {
    signInAsSuperadmin();
    $account = BillingAccount::factory()->create();
    planFor($account, ['profile' => Profile::Direct, 'seats' => 150]);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->assertSee('Requiere cotización')
        ->assertSee('1 empresa requiere cotización');
});

it('shows support the portfolio without the buttons that change it', function (): void {
    $this->actingAs(support());
    $account = BillingAccount::factory()->create();
    planFor($account, ['profile' => Profile::Direct]);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->assertSee('$4,900')
        ->assertDontSee('Editar plan')
        ->assertDontSee('Vincular empresa')
        ->assertDontSee('Editar cuenta');
});

it('shows the next amount before saving a plan, and saves it', function (): void {
    signInAsSuperadmin();
    [$tenant, $account] = paidCompany('consultant');
    $tenant->update(['trade_name' => 'Cliente Nuevo']);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->assertSee('Sin plan asignado')
        ->click('Asignar plan')
        ->assertSee('Cambiar el plan no cambia el acceso de la empresa')
        ->click('[name="profile"][value="channel_compliance"]')
        ->fill('seats', '50')
        ->assertSee('Banda de canal hasta 50 personas: $2,490')
        ->fill('seats', '100')
        ->assertSee('Banda de canal hasta 100 personas: $3,990')
        ->assertSee('IVA')
        ->press('Guardar plan')
        ->assertSee('Plan asignado.')
        ->assertSee('Canal Cumplimiento')
        ->assertSee('$3,990');

    expect(Subscription::query()->sole())->seats->toBe(100);
});

it('shows $2,490 to $3,990 when a channel company goes from 50 to 51 people, before it is saved', function (): void {
    signInAsSuperadmin();
    [$tenant, $account] = paidCompany('consultant');
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan' => Plan::Cumplimiento, 'profile' => Profile::ChannelCompliance, 'seats' => 50, 'centers' => 2]);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->click('Editar plan')
        ->assertSee('Banda de canal hasta 50 personas: $2,490')
        ->fill('seats', '51')
        ->assertSee('De $2,490 a $3,990 al mes');

    expect(Subscription::query()->sole()->seats)->toBe(50);
});

it('asks for a quotation in the preview above the limit, and refuses a channel rate on a direct account', function (): void {
    signInAsSuperadmin();
    [, $account] = paidCompany('direct');

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->click('Asignar plan')
        ->assertDontSee('Canal Cumplimiento')
        ->fill('seats', '101')
        ->assertSee('Más de 100 personas: requiere cotización')
        ->assertSee('Pon un precio negociado para fijar el importe.');
});

it('shows the extra charges of the account with who approved each', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    [$tenant, $account] = paidCompany();
    $tenant->update(['trade_name' => 'Cliente Nuevo']);
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'profile' => Profile::Direct]);
    SubscriptionCharge::factory()->for($subscription)->create(['tenant_id' => $tenant->id, 'description' => 'Implementación', 'amount_cents' => 1_250_000, 'charge_on' => '2026-12-15']);
    SubscriptionCharge::factory()->recurring()->for($subscription)->create(['tenant_id' => $tenant->id, 'description' => 'Acompañamiento', 'amount_cents' => 150_050, 'charge_on' => '2026-12-01', 'ends_on' => '2027-05-31']);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}", $dark, $mobile)
        ->assertSee('Cargos extra')
        ->assertSee('Implementación')
        ->assertSee('$12,500')
        ->assertSee('Desde 15 dic 2026')
        ->assertSee('Acompañamiento')
        ->assertSee('$1,500.50')
        ->assertSee('1 dic 2026 al 31 may 2027')
        ->assertSee('Recurrente')
        ->assertSee('Una sola vez')
        ->assertSee('Agregar cargo');
})->with($looks);

it('adds a charge to a company with a plan, and lists it', function (): void {
    signInAsSuperadmin();
    [$tenant, $account] = paidCompany();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'profile' => Profile::Direct]);

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->click('Agregar cargo')
        ->assertSee('Queda a tu nombre como quien lo aprobó.')
        ->click('[name="kind"][value="recurring"]')
        ->assertSee('Termina el (opcional)')
        ->fill('description', 'Acompañamiento')
        ->fill('scope', 'Una sesión al mes')
        ->fill('amount', '1500')
        ->press('Guardar cargo')
        ->assertSee('Cargo agregado.')
        ->assertSee('Una sesión al mes')
        ->assertSee('$1,500');

    expect(SubscriptionCharge::query()->sole())->amount_cents->toBe(150_000);
});

it('does not offer a charge to a company with no plan', function (): void {
    signInAsSuperadmin();
    [, $account] = paidCompany();

    visitPage('billing/account', "/admin/billing/accounts/{$account->id}")
        ->assertSee('Asignar plan')
        ->assertDontSee('Agregar cargo');
});

it('moves a company to another account after showing what it would do, and keeps the history on both', function (): void {
    signInAsSuperadmin();
    [$tenant, $from] = paidCompany('consultant');
    $tenant->update(['trade_name' => 'Cliente Movido']);
    $to = BillingAccount::factory()->create(['legal_name' => 'Grupo Directo Destino S.A.', 'rfc' => 'GDD010101AB1']);

    visitPage('billing/account', "/admin/billing/accounts/{$from->id}")
        ->click('Trasladar')
        ->assertSee('La empresa conserva su plan y su gente conserva su acceso')
        ->click('#transfer-to_account')
        ->type('[role="combobox"][aria-controls="transfer-to_account-options"]', 'destino')
        ->assertSee('Grupo Directo Destino S.A. · GDD010101AB1')
        ->keys('[role="combobox"][aria-controls="transfer-to_account-options"]', 'Enter')
        ->fill('reason', 'Contrata directo')
        ->assertSee('Pasa de')
        ->assertSee('Trasladar una empresa no da ni quita acceso a nadie')
        ->press('Trasladar empresa')
        ->assertSee('Empresa trasladada.')
        ->assertSee('Cliente Movido')
        ->assertSee('Antes la pagaba');

    visitPage('billing/account', "/admin/billing/accounts/{$from->id}")
        ->assertSee('Traslados')
        ->assertSee('Salió hacia Grupo Directo Destino S.A.')
        ->assertSee('Contrata directo');
});
