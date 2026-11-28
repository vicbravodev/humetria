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

it('opens on the list, and on the funnel when asked for it', function (): void {
    DemoRequest::factory()->create();
    $this->actingAs(superadmin());

    $this->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('filters.view', 'list')->where('board', null)->where('demoRequests.total', 1));

    $this->get(route('demo-requests.index', ['view' => 'board']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.view', 'board')->where('demoRequests', null)->has('board', 4));

    $this->get(route('demo-requests.index', ['view' => 'kanban']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.view', 'list'));
});

it('lays out the funnel in its four open stages, each with its total and its five newest requests', function (): void {
    DemoRequest::factory()->count(7)->sequence(fn ($sequence) => ['name' => "Nueva {$sequence->index}", 'created_at' => now()->subHours($sequence->index)])->create();
    DemoRequest::factory()->create(['name' => 'Agendada', 'status' => DemoStatus::Scheduled]);
    DemoRequest::factory()->create(['status' => DemoStatus::Converted]);
    DemoRequest::factory()->create(['status' => DemoStatus::Discarded]);

    $this->actingAs(superadmin())->get(route('demo-requests.index', ['view' => 'board']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.0.status', 'new')
            ->where('board.0.label', 'Nuevas')
            ->where('board.0.total', 7)
            ->has('board.0.cards', 5)
            ->where('board.0.cards.0.name', 'Nueva 0')
            ->where('board.0.cards.4.name', 'Nueva 4')
            ->where('board.1.status', 'contacted')
            ->where('board.1.total', 0)
            ->has('board.1.cards', 0)
            ->where('board.2.status', 'scheduled')
            ->where('board.2.label', 'Demo agendada')
            ->where('board.2.cards.0.name', 'Agendada')
            ->where('board.3.status', 'trial')
            ->where('board.3.label', 'Prueba activa'));
});

it('reads the funnel in six queries: the role, the permission cache, the counts, the cards and their owners', function (): void {
    $owner = support();
    DemoRequest::factory()->count(30)->create(['owner_user_id' => $owner->id]);
    $superadmin = superadmin()->fresh();

    $this->expectsDatabaseQueryCount(6);
    $this->actingAs($superadmin)->get(route('demo-requests.index', ['view' => 'board']))->assertOk();
});

it('searches nothing when nothing is typed', function (): void {
    DemoRequest::factory()->count(2)->create();

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('filters.q', '')->where('demoRequests.total', 2));
});
