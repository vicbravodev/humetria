<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Enums\Workforce;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Carbon;

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the superadmin every demo request with its contact links', function (bool $dark, bool $mobile): void {
    signInAsSuperadmin();
    DemoRequest::factory()->create([
        'name' => 'Ana López',
        'email' => 'ana@empresa.mx',
        'whatsapp' => '+525512345678',
        'company' => 'Empresa Ejemplo',
        'profile' => DemoProfile::Consultant,
        'workforce' => Workforce::From51To100,
        'tools' => [Tool::Nom035, Tool::LeySilla],
        'message' => 'Queremos aplicar la Guía III este año.',
        'created_at' => Carbon::parse('2026-10-27 16:05:00', 'UTC'),
    ]);

    visitPage('demos/index', '/admin/demo-requests', $dark, $mobile)
        ->assertSee('Solicitudes de demo')
        ->assertSee('1 solicitud')
        ->assertSee('Ana López')
        ->assertSee('Empresa Ejemplo')
        ->assertSee('Consultor')
        ->assertSee('51–100 trabajadores')
        ->assertSee('Ley Silla')
        ->assertSee('Queremos aplicar la Guía III este año.')
        ->assertSee('27 oct 2026, 10:05')
        ->assertAttribute('a[href="mailto:ana@empresa.mx"]', 'href', 'mailto:ana@empresa.mx')
        ->assertAttribute('a[href="https://wa.me/525512345678"]', 'href', 'https://wa.me/525512345678');
})->with($looks);

it('says so when nobody has asked for a demo yet', function (bool $dark): void {
    signInAsSuperadmin();

    visitPage('demos/index', '/admin/demo-requests', $dark)
        ->assertSee('Aún no hay solicitudes');
})->with(['light' => false, 'dark' => true]);

it('moves between pages of 25 requests', function (): void {
    signInAsSuperadmin();
    // Fixed text: Faker's company names can carry English words the page check rejects.
    DemoRequest::factory()->count(25)->create(['name' => 'Ana López', 'company' => 'Empresa Ejemplo', 'created_at' => now()]);
    DemoRequest::factory()->create(['name' => 'Rosa Medina', 'company' => 'Empresa Ejemplo', 'created_at' => now()->subYear()]);

    visitPage('demos/index', '/admin/demo-requests')
        ->assertSee('Página 1 de 2')
        ->assertDontSee('Rosa Medina')
        ->click('Ver más antiguas')
        ->assertSee('Página 2 de 2')
        ->assertSee('Rosa Medina')
        ->assertSee('Ver más recientes');
});

it('links the console from the sidebar only for the superadmin', function (): void {
    signInAsSuperadmin();

    visit('/admin')
        ->click('Solicitudes de demo')
        ->assertPathIs('/admin/demo-requests');

    $this->actingAs(User::factory()->create());

    visit('/admin')->assertDontSee('Solicitudes de demo');
});

it('takes a phone to the request chosen, below the list', function (): void {
    signInAsSuperadmin();
    DemoRequest::factory()->create(['name' => 'Ana López', 'created_at' => now()]);
    DemoRequest::factory()->count(6)->create(['created_at' => now()->subDays(2)]);
    DemoRequest::factory()->create(['name' => 'Rosa Medina', 'message' => 'Después', 'created_at' => now()->subDays(3)]);

    visit('/admin/demo-requests')->on()->mobile()
        ->click('Rosa Medina')
        ->assertSeeIn('@demo-request-detail', 'Rosa Medina')
        ->assertScript('() => document.activeElement.getAttribute("data-test")', 'demo-request-detail')
        ->assertScript('() => { const box = document.querySelector(\'[data-test="demo-request-detail"]\').getBoundingClientRect(); return box.top >= 0 && box.top < window.innerHeight; }')
        ->assertNoJavaScriptErrors();
});

it('opens the newest request and shows another one when chosen from the list', function (): void {
    signInAsSuperadmin();
    DemoRequest::factory()->create(['name' => 'Ana López', 'company' => 'Empresa Ejemplo', 'message' => 'Primero', 'created_at' => now()]);
    DemoRequest::factory()->create(['name' => 'Rosa Medina', 'company' => 'Empresa Ejemplo', 'message' => 'Después', 'created_at' => now()->subDay()]);

    visitPage('demos/index', '/admin/demo-requests')
        ->assertSeeIn('@demo-request-detail', 'Ana López')
        ->assertSee('Primero')
        ->click('Rosa Medina')
        ->assertSeeIn('@demo-request-detail', 'Rosa Medina')
        ->assertSee('Después')
        ->assertAttribute('li button[aria-current="true"]', 'aria-current', 'true')
        ->assertNoJavaScriptErrors();
});
