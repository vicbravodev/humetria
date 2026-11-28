<?php

declare(strict_types=1);

use App\Modules\Demos\Enums\DemoStatus;
use App\Modules\Demos\Enums\Workforce;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('says where each request came from, as the funnel names it', function (callable $make, string $origin): void {
    $make();

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('demoRequests.data.0.origin', $origin));
})->with([
    'an ad click' => [fn () => DemoRequest::factory()->withAdClick()->create(), 'Anuncio'],
    'a paid medium' => [fn () => DemoRequest::factory()->fromCampaign('google', 'CPC')->create(), 'Anuncio'],
    'a campaign of ours' => [fn () => DemoRequest::factory()->fromCampaign('boletin', 'correo')->create(), 'Campaña'],
    'a search engine' => [fn () => DemoRequest::factory()->referredBy('www.google.com.mx')->create(), 'Orgánico'],
    'another site' => [fn () => DemoRequest::factory()->referredBy('blog.ejemplo.mx')->create(), 'Referido'],
    'nothing known' => [fn () => DemoRequest::factory()->create(), 'Sin dato'],
]);

it('narrows the list by origin, owner and size in the server, and says which', function (): void {
    $owner = support();
    DemoRequest::factory()->withAdClick()->create(['name' => 'Ana', 'owner_user_id' => $owner->id, 'workforce' => Workforce::From51To100]);
    DemoRequest::factory()->withAdClick()->create(['name' => 'Beto', 'workforce' => Workforce::From51To100]);
    DemoRequest::factory()->create(['name' => 'Carla', 'owner_user_id' => $owner->id, 'workforce' => Workforce::From51To100]);
    DemoRequest::factory()->withAdClick()->create(['name' => 'Dora', 'owner_user_id' => $owner->id, 'workforce' => Workforce::UpTo15]);
    $this->actingAs(superadmin());

    $this->get(route('demo-requests.index', ['origin' => 'ad', 'owner' => (string) $owner->id, 'size' => '51_100']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.origin', 'ad')
            ->where('filters.owner', (string) $owner->id)
            ->where('filters.size', '51_100')
            ->where('demoRequests.total', 1)
            ->where('demoRequests.data.0.name', 'Ana'));

    $this->get(route('demo-requests.index', ['owner' => 'none']))
        ->assertInertia(fn (Assert $page) => $page->where('filters.owner', 'none')->where('demoRequests.total', 1)->where('demoRequests.data.0.name', 'Beto'));
});

it('ignores a filter it does not know', function (): void {
    DemoRequest::factory()->count(2)->create();

    $this->actingAs(superadmin())->get(route('demo-requests.index', ['origin' => 'tv', 'owner' => 'alguien', 'size' => '7']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.origin', '')
            ->where('filters.owner', '')
            ->where('filters.size', '')
            ->where('demoRequests.total', 2));
});

it('narrows the funnel by what was searched and the filters, and counts each stage after them', function (): void {
    DemoRequest::factory()->withAdClick()->count(2)->create(['company' => 'Clínica Santa Fe']);
    DemoRequest::factory()->create(['company' => 'Clínica del Valle']);
    DemoRequest::factory()->withAdClick()->create(['company' => 'Frutas Ramos', 'status' => DemoStatus::Contacted]);
    $this->actingAs(superadmin());

    $this->get(route('demo-requests.index', ['view' => 'board', 'q' => 'clinica', 'origin' => 'ad']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.q', 'clinica')
            ->where('board.0.total', 2)
            ->has('board.0.cards', 2)
            ->where('board.1.total', 0)
            ->has('board.1.cards', 0));
});

it('gives each card how long it has waited, or when its demo is, with how urgent it looks', function (array $columns, string $label, string $tone): void {
    $this->travelTo(Carbon::parse('2026-11-25 18:00:00', 'UTC'));
    DemoRequest::factory()->create($columns);

    $this->actingAs(superadmin())->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page->where('demoRequests.data.0.waiting', ['label' => $label, 'tone' => $tone]));
})->with([
    'new, just now' => [['created_at' => '2026-11-25 17:40:00'], 'Hace menos de 1 h', 'secondary'],
    'new, 6 hours' => [['created_at' => '2026-11-25 12:00:00'], 'Hace 6 h', 'secondary'],
    'new, a day' => [['created_at' => '2026-11-24 17:00:00'], 'Hace 25 h', 'warning'],
    'new, past 48 hours' => [['created_at' => '2026-11-23 14:00:00'], 'Hace 52 h', 'destructive'],
    'new, days' => [['created_at' => '2026-11-20 18:00:00'], 'Hace 5 d', 'destructive'],
    'contacted today' => [['status' => DemoStatus::Contacted, 'created_at' => '2026-11-20 18:00:00', 'handled_at' => '2026-11-25 15:00:00'], 'Contactada hoy', 'success'],
    'contacted 3 days ago' => [['status' => DemoStatus::Contacted, 'created_at' => '2026-11-20 18:00:00', 'handled_at' => '2026-11-22 15:00:00'], 'Contactada hace 3 d', 'warning'],
    'scheduled' => [['status' => DemoStatus::Scheduled, 'scheduled_at' => '2026-11-26 17:00:00'], 'jue 26 nov, 11:00', 'default'],
    'in trial' => [['status' => DemoStatus::Trial, 'created_at' => '2026-11-15 18:00:00'], 'Llegó hace 10 d', 'secondary'],
    'converted' => [['status' => DemoStatus::Converted], 'Convertida', 'success'],
    'discarded' => [['status' => DemoStatus::Discarded], 'Descartada', 'secondary'],
]);

it('reads a filtered funnel in the same six queries', function (): void {
    $owner = support();
    DemoRequest::factory()->count(10)->withAdClick()->create(['owner_user_id' => $owner->id, 'company' => 'Clínica', 'workforce' => Workforce::From51To100]);
    $superadmin = superadmin()->fresh();

    $this->expectsDatabaseQueryCount(6);
    $this->actingAs($superadmin)->get(route('demo-requests.index', ['view' => 'board', 'origin' => 'ad', 'owner' => (string) $owner->id, 'size' => '51_100', 'q' => 'clinica']))
        ->assertInertia(fn (Assert $page) => $page->where('board.0.total', 10));
});
