<?php

declare(strict_types=1);

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Models\DemoRequest;
use Inertia\Testing\AssertableInertia as Assert;

it('searches the list by contact, email, company or WhatsApp, ignoring case and accents', function (string $search, string $found): void {
    DemoRequest::factory()->create(['name' => 'José Pérez', 'email' => 'jose@acme.mx', 'company' => 'Acme Industrial', 'whatsapp' => '+525512345678']);
    DemoRequest::factory()->create(['name' => 'Rosa Medina', 'email' => 'rosa@lira.mx', 'company' => 'Despacho Lira', 'whatsapp' => '+525587654321']);

    $this->actingAs(superadmin())->get(route('demo-requests.index', ['q' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', $search)
            ->where('demoRequests.total', 1)
            ->where('demoRequests.data.0.name', $found)
            ->where('counts.all', 2));
})->with([
    'a name without accents' => ['jose perez', 'José Pérez'],
    'an email' => ['rosa@lira', 'Rosa Medina'],
    'a company' => ['ACME', 'José Pérez'],
    'a WhatsApp as people write it' => ['55 8765 4321', 'Rosa Medina'],
]);

it('searches inside the status chosen and keeps both in the links between pages', function (): void {
    DemoRequest::factory()->count(26)->create(['company' => 'Acme', 'status' => DemoStatus::Contacted]);
    DemoRequest::factory()->create(['company' => 'Acme']);

    $this->actingAs(superadmin())->get(route('demo-requests.index', ['status' => 'contacted', 'q' => 'acme']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoRequests.total', 26)
            ->where('demoRequests.next_page_url', route('demo-requests.index', ['status' => 'contacted', 'q' => 'acme', 'page' => 2])));
});

it('searches nothing when nothing is typed', function (): void {
    DemoRequest::factory()->count(2)->create();

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', '')->where('demoRequests.total', 2));
});
