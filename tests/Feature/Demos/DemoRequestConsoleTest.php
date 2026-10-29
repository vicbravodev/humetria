<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Enums\Workforce;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('lives at /admin/demo-requests', function (): void {
    expect(route('demo-requests.index', absolute: false))->toBe('/admin/demo-requests');
});

it('sends guests to the login', function (): void {
    $this->get(route('demo-requests.index'))->assertRedirect(route('login'));
});

it('answers 404 to anyone who is not a superadmin, so the console does not reveal itself', function (): void {
    DemoRequest::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('demo-requests.index'))
        ->assertNotFound();
});

it('lists every field of a demo request for the superadmin, labelled and in Mexico City time', function (): void {
    $demoRequest = DemoRequest::factory()->create([
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

    $this->actingAs(superadmin())
        ->get(route('demo-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('demos/index')
            ->where('demoRequests.total', 1)
            ->where('demoRequests.data', [[
                'id' => $demoRequest->id,
                'name' => 'Ana López',
                'email' => 'ana@empresa.mx',
                'whatsapp' => '+525512345678',
                'company' => 'Empresa Ejemplo',
                'profile' => 'Consultor',
                'workforce' => '51–100',
                'tools' => ['NOM-035', 'Ley Silla'],
                'message' => 'Queremos aplicar la Guía III este año.',
                'received_at' => '27 oct 2026, 10:05',
            ]]));
});

it('labels every profile and headcount range', function (DemoProfile|Workforce|Tool $case, string $label): void {
    expect($case->label())->toBe($label);
})->with([
    [DemoProfile::Company, 'Empresa'],
    [DemoProfile::Consultant, 'Consultor'],
    [Workforce::UpTo15, '1–15'],
    [Workforce::From16To50, '16–50'],
    [Workforce::From51To100, '51–100'],
    [Workforce::From101To500, '101–500'],
    [Workforce::MoreThan500, 'Más de 500'],
    [Tool::Nom035, 'NOM-035'],
    [Tool::Nom037, 'NOM-037'],
    [Tool::LeySilla, 'Ley Silla'],
]);

it('shows the newest requests first', function (): void {
    DemoRequest::factory()->create(['name' => 'Antigua', 'created_at' => now()->subDay()]);
    DemoRequest::factory()->create(['name' => 'Reciente', 'created_at' => now()]);

    $this->actingAs(superadmin())
        ->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoRequests.data.0.name', 'Reciente')
            ->where('demoRequests.data.1.name', 'Antigua'));
});

it('pages the list 25 at a time', function (): void {
    DemoRequest::factory()->count(26)->create();

    $this->actingAs(superadmin())
        ->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('demoRequests.data', 25)
            ->where('demoRequests.total', 26)
            ->where('demoRequests.prev_page_url', null)
            ->where('demoRequests.next_page_url', route('demo-requests.index', ['page' => 2])));

    $this->actingAs(superadmin())
        ->get(route('demo-requests.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('demoRequests.data', 1)
            ->where('demoRequests.next_page_url', null));
});

it('renders the empty console when nobody has asked for a demo yet', function (): void {
    $this->actingAs(superadmin())
        ->get(route('demo-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('demoRequests.data', 0)
            ->where('demoRequests.total', 0));
});

it('reads the page in four queries: the user roles, the permission cache, the count and the page', function (): void {
    DemoRequest::factory()->count(30)->create();
    // A fresh instance, as a real request gets: its roles are not loaded yet. Spatie keeps the permissions
    // in the cache store, so production skips that query between requests; tests use the array store.
    $superadmin = superadmin()->fresh();

    $this->expectsDatabaseQueryCount(4);
    $this->actingAs($superadmin)->get(route('demo-requests.index'))->assertOk();
});

it('tells the frontend whether the user is a superadmin, to show the console link', function (): void {
    $this->actingAs(superadmin())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isSuperadmin', true));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isSuperadmin', false));
});
