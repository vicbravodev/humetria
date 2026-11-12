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

it('shows how a mark ends up and what changes, before RH saves', function (): void {
    $url = signInToPanel();

    visit("{$url}/attendance/policy")
        ->assertSee('Así queda una marca')
        ->assertSee('Sin cambios por guardar')
        ->assertSee('Con QR, pero fuera de todas las zonas')
        ->assertSee('No se guarda la marca')
        ->assertDontSee('Se guarda sin zona, señalada')
        ->click('Permitir y señalar')
        ->assertSee('Se guarda sin zona, señalada')
        ->assertDontSee('No se guarda la marca')
        ->assertSee('1 cambio sin guardar · quedará en la bitácora')
        ->fill('#policy-max_accuracy_m', '60')
        ->assertSee('Con QR, pero con ubicación peor que 60 m')
        ->assertSee('2 cambios sin guardar · quedarán en la bitácora')
        ->click('Descartar')
        ->assertSee('Sin cambios por guardar')
        ->assertSee('No se guarda la marca')
        ->assertScript('() => document.querySelector("#policy-max_accuracy_m").value', '100')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');
});

it('shows a viewer the policy without letting them change it', function (): void {
    $url = signInToPanel();
    $tenant = Tenant::query()->where('slug', 'acme')->sole();
    $this->actingAs(memberOf($tenant, TenantRole::Viewer));

    visitPage('attendance/policy', "{$url}/attendance/policy")
        ->assertSee('Solo el dueño y RH la cambian')
        ->assertSee('Solo lectura')
        ->assertSee('Tu rol puede ver esta pantalla, pero no modificar nada. Pide al dueño o a RH que te den permiso de edición.')
        ->assertDontSee('Guardar política')
        ->assertScript('() => document.querySelector("#policy-retention_months").matches(":disabled")', true);
});

it('takes RH from the sidebar to the devices, the schedules and the policy, lighting each', function (): void {
    $url = signInToPanel();

    visit($url)
        ->click('Cumple la norma')
        ->click('[data-sidebar="sidebar"] >> text=Asistencia')
        ->click('[data-sidebar="sidebar"] >> text=Dispositivos')
        ->waitForText('Envíale su enlace de alta')
        ->assertPathIs("{$url}/attendance/devices")
        ->assertAttribute('[data-sidebar="sidebar"] a[href$="/attendance/devices"]', 'data-active', 'true')
        ->click('[data-sidebar="sidebar"] >> text=Horarios')
        ->waitForText('lo que trabaje de más cuenta como tiempo extra')
        ->assertPathIs("{$url}/attendance/schedules")
        ->assertAttribute('[data-sidebar="sidebar"] a[href$="/attendance/schedules"]', 'data-active', 'true')
        ->click('[data-sidebar="sidebar"] >> text=Política')
        ->waitForText('Marcas fuera de zona')
        ->assertPathIs("{$url}/attendance/policy")
        ->assertAttribute('[data-sidebar="sidebar"] a[href$="/attendance/policy"]', 'data-active', 'true')
        ->assertNoJavaScriptErrors();
});
