<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Tool;
use App\Modules\Demos\Enums\Workforce;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DemoRequest::class);

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

it('keeps the query string in the links between pages', function (): void {
    DemoRequest::factory()->count(26)->create();

    $this->actingAs(superadmin())
        ->get(route('demo-requests.index', ['page' => 1, 'sort' => 'latest']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoRequests.next_page_url', route('demo-requests.index', ['sort' => 'latest', 'page' => 2])));
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
                // The pipeline (F7): where it stands, the team's note and the company it became.
                'status' => 'new',
                'status_label' => 'Nueva',
                'note' => null,
                'tenant_id' => null,
                // Where it came from (piece 8): nobody knew, so "no data", which is not direct traffic.
                'arrived_by' => null,
                'first_contact' => null,
            ]]));
});

it('labels every profile and headcount range', function (DemoProfile|Workforce|Tool $case, string $label): void {
    expect($case->label())->toBe($label);
})->with([
    [DemoProfile::Company, 'Empresa'],
    [DemoProfile::Consultant, 'Consultor'],
    [DemoProfile::Union, 'Sindicato'],
    [Workforce::UpTo15, '1–15'],
    [Workforce::From16To50, '16–50'],
    [Workforce::From51To100, '51–100'],
    [Workforce::From101To500, '101–500'],
    [Workforce::MoreThan500, 'Más de 500'],
    [Tool::Nom035, 'NOM-035'],
    [Tool::Nom037, 'NOM-037'],
    [Tool::LeySilla, 'Ley Silla'],
    [Tool::Complaints, 'Buzón de quejas'],
    [Tool::Compensation, 'Compensación'],
    [Tool::Attendance, 'Registro de jornada'],
    [Tool::Leave, 'Vacaciones y permisos'],
    [Tool::OrgChart, 'Organigrama'],
    [Tool::Engagement, 'Clima y pulso'],
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

it('reads the page in six queries: the superadmin role, the permission cache (permissions and their roles), the count of each status, the count and the page', function (): void {
    DemoRequest::factory()->count(30)->create();
    // A fresh instance, as a real request gets: its roles are not loaded yet. Spatie keeps the permissions
    // in the cache store, so production skips that query between requests; tests use the array store.
    $superadmin = superadmin()->fresh();

    $this->expectsDatabaseQueryCount(6);
    $this->actingAs($superadmin)->get(route('demo-requests.index'))->assertOk();
});

it('tells the frontend whether the user is a superadmin, to show the console link', function (): void {
    $this->actingAs(superadmin())
        ->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isSuperadmin', true));

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isSuperadmin', false));
});

it('says where a request arrived by: source and medium joined, the campaign after, else the referrer host, else nothing', function (array $columns, ?string $label): void {
    expect(DemoRequest::factory()->make($columns)->arrivedBy())->toBe($label);
})->with([
    'source, medium and campaign' => [['utm_source' => 'google', 'utm_medium' => 'cpc', 'utm_campaign' => 'nom-035'], 'google / cpc · nom-035'],
    'source and medium' => [['utm_source' => 'google', 'utm_medium' => 'cpc'], 'google / cpc'],
    'source only' => [['utm_source' => 'newsletter'], 'newsletter'],
    'medium only' => [['utm_medium' => 'email'], 'email'],
    'campaign only' => [['utm_campaign' => 'nom-035'], 'nom-035'],
    'source and campaign' => [['utm_source' => 'linkedin', 'utm_campaign' => 'ley-silla'], 'linkedin · ley-silla'],
    'a utm wins over the referrer host' => [['utm_source' => 'google', 'referrer_host' => 'www.google.com'], 'google'],
    'only a referrer host' => [['referrer_host' => 'www.google.com'], 'www.google.com'],
    'a value of 0 is still a value' => [['utm_source' => '0', 'utm_medium' => '0', 'utm_campaign' => '0'], '0 / 0 · 0'],
    'nothing is no data, never direct' => [[], null],
]);

it('says the first contact only when it is not the same channel the request arrived by', function (array $columns, ?string $label): void {
    expect(DemoRequest::factory()->make($columns)->firstContact())->toBe($label);
})->with([
    'the same campaign' => [['utm_source' => 'google', 'first_utm_source' => 'google'], null],
    'the same host' => [['referrer_host' => 'www.google.com', 'first_referrer_host' => 'www.google.com'], null],
    'a different campaign' => [['utm_source' => 'google', 'utm_medium' => 'cpc', 'first_utm_source' => 'newsletter', 'first_utm_medium' => 'email', 'first_utm_campaign' => 'nom-037'], 'newsletter / email · nom-037'],
    'a host first and a campaign later' => [['utm_source' => 'google', 'first_referrer_host' => 'www.bing.com'], 'www.bing.com'],
    'a campaign first and no later channel' => [['first_utm_source' => 'google'], 'google'],
    'no first contact' => [['utm_source' => 'google'], null],
    'nothing at all' => [[], null],
]);

it('gives the console the channel a request arrived by and, when it differs, its first contact', function (): void {
    DemoRequest::factory()->fromCampaign('google', 'cpc', 'nom-035')->create([
        'first_utm_source' => 'newsletter', 'first_utm_medium' => 'email', 'first_utm_campaign' => null,
    ]);

    $this->actingAs(superadmin())
        ->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoRequests.data.0.arrived_by', 'google / cpc · nom-035')
            ->where('demoRequests.data.0.first_contact', 'newsletter / email'));
});

it('shows no first contact when it is the same channel, and the host when there is no campaign', function (): void {
    DemoRequest::factory()->referredBy('www.google.com')->create();

    $this->actingAs(superadmin())
        ->get(route('demo-requests.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoRequests.data.0.arrived_by', 'www.google.com')
            ->where('demoRequests.data.0.first_contact', null));
});

it('lists a request that left its profile, size and tools empty, without labels to show', function (): void {
    DemoRequest::factory()->create(['profile' => null, 'workforce' => null, 'tools' => []]);

    $this->actingAs(superadmin())
        ->get(route('demo-requests.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoRequests.data.0.profile', null)
            ->where('demoRequests.data.0.workforce', null)
            ->where('demoRequests.data.0.tools', []));
});
