<?php

declare(strict_types=1);

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;

it('shows the funnel with its figures and the request chosen beside it', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    DemoRequest::factory()->create(['name' => 'Lucía Vega', 'company' => 'Clínica Santa Fe', 'created_at' => now()->subDays(3)]);
    DemoRequest::factory()->create(['name' => 'Ana López', 'company' => 'Hoteles Costa', 'status' => DemoStatus::Scheduled, 'scheduled_at' => now()->addDays(2)]);
    // More new ones than a column shows: the rest are a link away. Fixed text: Faker's words can be English.
    DemoRequest::factory()->count(5)->create(['name' => 'Rosa Medina', 'company' => 'Empresa Ejemplo', 'created_at' => now()->subDays(5)]);

    visitPage('demos/index', '/admin/demo-requests?view=board', $dark, $mobile)
        ->assertSee('Embudo')
        ->assertSee('Tiempo de primera respuesta')
        ->assertSee('6 con más de 48 h')
        ->assertSee('Demo agendada')
        ->assertSee('Prueba activa')
        ->assertSee('Ver todas (6)')
        ->assertSeeIn('@demo-request-detail', 'Lucía Vega')
        ->assertSee('Agendar demo')
        ->assertSee('Abrir sala de demo')
        ->assertSee('Crear empresa con estos datos')
        ->assertSee('Descartar con motivo')
        ->click('Hoteles Costa')
        ->assertSeeIn('@demo-request-detail', 'Ana López')
        ->assertScript(spilledOut(), '');
})->with([
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('discards a request with its reason from the funnel', function (): void {
    signInAsSuperadmin();
    $request = DemoRequest::factory()->create(['name' => 'Lucía Vega', 'company' => 'Clínica Santa Fe']);

    visitPage('demos/index', '/admin/demo-requests?view=board')
        ->click('Descartar con motivo')
        ->type('reason', 'Buscaba empleo')
        ->press('Descartar')
        ->assertSee('Solicitud descartada.')
        ->assertNoJavaScriptErrors();

    expect($request->fresh()->status)->toBe(DemoStatus::Discarded);
});
