<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Models\AttendancePolicy;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;

it('shows RH the policy with its defaults explained', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('attendance/policy', "{$url}/attendance/policy", $dark, $mobile)
        ->assertSee('Política')
        ->assertSee('Valores de inicio')
        ->assertSee('Bloquear (recomendado)')
        ->assertSee('nadie puede marcar fuera de su centro')
        ->assertSee('Hoy Humetría no borra nada');
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, phone' => [false, true],
    'dark, phone' => [true, true],
]);

it('lets RH change the policy and keeps it', function (): void {
    $url = signInToPanel();

    visit("{$url}/attendance/policy")
        ->click('Permitir y señalar')
        ->fill('#policy-max_accuracy_m', '60')
        ->click('Pedir el QR a quien marca en el centro')
        ->fill('#policy-retention_months', '24')
        ->click('Guardar política')
        ->waitForText('Política guardada')
        ->assertDontSee('Valores de inicio')
        ->assertNoJavaScriptErrors();

    workIn(Tenant::query()->where('slug', 'acme')->sole());
    expect(AttendancePolicy::query()->sole())
        ->out_of_zone->toBe(OutOfZoneRule::AllowAndFlag)->max_accuracy_m->toBe(60)->require_qr_onsite->toBeFalse()
        ->trusted_may_use_browser->toBeTrue()->retention_months->toBe(24);
});

it('shows a viewer the policy without letting them change it', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $this->actingAs(memberOf($tenant, TenantRole::Viewer));

    visitPage('attendance/policy', "{$url}/attendance/policy")
        ->assertSee('Solo el dueño y RH la cambian')
        ->assertDontSee('Guardar política')
        ->assertScript('() => document.querySelector("#policy-retention_months").matches(":disabled")', true);
});
