<?php

declare(strict_types=1);

use App\Actions\RecordAudit;
use App\Models\User;
use App\Modules\Billing\Contracts\CompanyPlans;
use App\Modules\Billing\Contracts\Receivables;
use App\Modules\Billing\Data\CompanyPayer;
use App\Modules\Billing\Data\PlanSummary;
use App\Modules\Tenancy\Actions\AddTenantNote;
use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantNote;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AddTenantNote::class);

/** Billing's answer about the plan, without its tables: only what its public contract says. */
function planOfConsole(?PlanSummary $plan): void
{
    app()->instance(CompanyPlans::class, new readonly class($plan) implements CompanyPlans
    {
        public function __construct(private ?PlanSummary $plan) {}

        public function current(string $tenantId): ?PlanSummary
        {
            return $this->plan;
        }
    });
}

it('shows who owns the company, who at Humetría answers for it and the plan Billing says it has', function (): void {
    $manager = support();
    $manager->update(['name' => 'Laura Soporte']);
    $tenant = Tenant::factory()->create(['owner_user_id' => $manager->id, 'created_at' => '2026-03-14 12:00 UTC']);
    $owner = memberOf($tenant, TenantRole::Owner);
    planOfConsole(new PlanSummary('Crecimiento', CarbonImmutable::parse('2026-03-14'), CarbonImmutable::parse('2026-12-01')));

    $this->actingAs(superadmin())->get(route('companies.show', $tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('company.owner', ['name' => $owner->name, 'email' => $owner->email])
        ->where('company.account_manager', ['id' => $manager->id, 'name' => 'Laura Soporte'])
        ->where('company.created_on', '14 mar 2026')
        ->where('plan', ['name' => 'Crecimiento', 'since' => '14 mar 2026', 'renews_on' => '1 dic 2026']));
});

it('shows the city of its first work center, its logo and the last time anyone of it signed in', function (): void {
    $tenant = Tenant::factory()->create(['logo_path' => 'logos/acme.png']);
    WorkCenter::factory()->for($tenant)->create(['municipality' => 'Monterrey', 'created_at' => '2026-01-01 12:00 UTC']);
    WorkCenter::factory()->for($tenant)->create(['municipality' => 'Apodaca', 'created_at' => '2026-02-01 12:00 UTC']);
    memberOf($tenant, TenantRole::Owner)->forceFill(['last_login_at' => '2026-11-20 18:00 UTC'])->save();
    memberOf($tenant, TenantRole::Hr)->forceFill(['last_login_at' => '2026-11-24 18:00 UTC'])->save();
    memberOf(Tenant::factory()->create(), TenantRole::Owner)->forceFill(['last_login_at' => '2026-11-25 18:00 UTC'])->save();

    $this->actingAs(superadmin())->get(route('companies.show', $tenant))->assertInertia(fn (Assert $page) => $page
        ->where('company.city', 'Monterrey')
        ->where('company.logo_url', Storage::disk(config()->string('filesystems.tenant_logos'))->url('logos/acme.png'))
        ->where('company.last_access', '24 nov 2026'));
});

it('leaves out the city, the logo and the last access when there are none', function (): void {
    $tenant = Tenant::factory()->create();
    memberOf($tenant, TenantRole::Owner);

    $this->actingAs(superadmin())->get(route('companies.show', $tenant))->assertInertia(fn (Assert $page) => $page
        ->where('company.city', null)->where('company.logo_url', null)->where('company.last_access', null));
});

it('tells the staff which account pays the company and what it owes past due, as Billing says', function (): void {
    app()->instance(Receivables::class, Mockery::mock(Receivables::class, fn ($mock) => $mock->shouldReceive('payerOf')
        ->andReturn(new CompanyPayer('acc-1', 'Textiles del Norte SA de CV', 'Directa', 1_280_000, 38))));

    foreach ([superadmin(), support()] as $user) {
        $this->actingAs($user)->get(route('companies.show', Tenant::factory()->create()))->assertInertia(fn (Assert $page) => $page
            ->where('payer', ['account_id' => 'acc-1', 'legal_name' => 'Textiles del Norte SA de CV', 'kind_label' => 'Directa', 'overdue_cents' => 1_280_000, 'days_past_due' => 38]));
    }
});

it('says no account pays the company when Billing has none', function (): void {
    $this->actingAs(superadmin())->get(route('companies.show', Tenant::factory()->create()))
        ->assertInertia(fn (Assert $page) => $page->where('payer', null));
});

it('says the company has no plan when Billing says so', function (): void {
    planOfConsole(null);

    $this->actingAs(superadmin())->get(route('companies.show', Tenant::factory()->create()))
        ->assertInertia(fn (Assert $page) => $page->where('plan', null)->where('company.owner', null)->where('company.account_manager', null));
});

it('lists its members with their role and its open invitations, a page at a time, and nothing of another company', function (): void {
    $tenant = Tenant::factory()->create();
    memberOf($tenant, TenantRole::Owner);
    memberOf($tenant, TenantRole::Hr);
    $other = Tenant::factory()->create();
    memberOf($other, TenantRole::Viewer);
    TenantInvitation::factory()->for($tenant)->create(['name' => 'Beto', 'email' => 'beto@acme.mx']);
    TenantInvitation::factory()->for($tenant)->accepted()->create();
    TenantInvitation::factory()->for($other)->create();

    $this->actingAs(superadmin())->get(route('companies.show', $tenant))->assertInertia(fn (Assert $page) => $page
        ->where('members.total', 2)
        ->where('members.data', fn ($rows): bool => collect($rows)->pluck('role')->sort()->values()->all() === ['Administra todo', 'Ayuda con las rutas'])
        ->where('invitations.total', 1)
        ->where('invitations.data.0.email', 'beto@acme.mx'));
});

it('pages the members twenty at a time under their own page name', function (): void {
    $tenant = Tenant::factory()->create();
    foreach (range(1, 21) as $i) {
        memberOf($tenant, TenantRole::Viewer);
    }

    $this->actingAs(superadmin())->get(route('companies.show', [$tenant, 'members_page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('members.data', 1)->where('members.total', 21));
});

it('keeps internal notes that only the staff writes and reads, newest first, and logs each', function (): void {
    $this->travelTo('2026-11-23 18:00 UTC');
    $tenant = Tenant::factory()->create();
    $helper = support();
    $helper->update(['name' => 'Laura Soporte']);
    Log::spy();

    $this->actingAs($helper)->post(route('companies.notes.store', $tenant), ['body' => 'Paga en quincena, avisar el 28'])
        ->assertRedirect(route('companies.show', $tenant));
    $this->travel(1)->hour();
    $this->actingAs(superadmin())->post(route('companies.notes.store', $tenant), ['body' => 'Pidió mover la campaña']);
    TenantNote::factory()->create(['tenant_id' => Tenant::factory()->create()->id, 'body' => 'De otra empresa']);

    Log::shouldHaveReceived('info')->with('tenancy.note_added', Mockery::on(fn (array $context): bool => $context['tenant_id'] === $tenant->id && ! array_key_exists('body', $context)))->twice();

    $this->actingAs($helper)->get(route('companies.show', $tenant))->assertInertia(fn (Assert $page) => $page
        ->where('notes.total', 2)
        ->where('notes.data.0.body', 'Pidió mover la campaña')
        ->where('notes.data.1', ['id' => TenantNote::query()->where('body', 'Paga en quincena, avisar el 28')->value('id'), 'body' => 'Paga en quincena, avisar el 28', 'author' => 'Laura Soporte', 'at' => '23 nov 2026']));
});

it('refuses a note to anyone outside the staff before reading it, and an empty or endless one', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(memberOf($tenant, TenantRole::Owner))->post(route('companies.notes.store', $tenant), [])->assertNotFound();
    $this->actingAs(superadmin())->post(route('companies.notes.store', $tenant), ['body' => ''])->assertSessionHasErrors(['body' => 'El campo nota es obligatorio.']);
    $this->actingAs(superadmin())->post(route('companies.notes.store', $tenant), ['body' => str_repeat('a', 2001)])->assertSessionHasErrors('body');
    $this->actingAs(superadmin())->post(route('companies.notes.store', 'a5f0e7d6-0000-4000-8000-000000000000'), ['body' => 'Nota'])->assertNotFound();

    expect(TenantNote::query()->count())->toBe(0);
});

it('suspends or archives from the risk zone only with a reason and the company name typed', function (string $restriction): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Textiles del Norte']);

    $this->actingAs(superadmin())
        ->post(route('companies.risk', $tenant), ['restriction' => $restriction, 'reason' => 'Saldo vencido', 'confirmation' => 'Textiles del Nor'])
        ->assertSessionHasErrors('confirmation');
    expect($tenant->fresh()?->restriction)->toBeNull();

    $this->actingAs(superadmin())
        ->post(route('companies.risk', $tenant), ['restriction' => $restriction, 'reason' => 'Saldo vencido'])
        ->assertSessionHasErrors(['confirmation' => 'El campo nombre de la empresa es obligatorio.']);

    $this->actingAs(superadmin())
        ->post(route('companies.risk', $tenant), ['restriction' => $restriction, 'reason' => 'Saldo vencido', 'confirmation' => ' Textiles del Norte '])
        ->assertRedirect(route('companies.show', $tenant));
    expect($tenant->fresh()?->restriction)->toBe(TenantRestriction::from($restriction))
        ->and($tenant->fresh()?->restriction_reason)->toBe('Saldo vencido');
})->with(['suspended', 'archived']);

it('takes neither read only nor a missing reason from the risk zone, nor anyone but the superadmin', function (): void {
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme']);

    $this->actingAs(superadmin())->post(route('companies.risk', $tenant), ['restriction' => 'read_only', 'reason' => 'x', 'confirmation' => 'Acme'])->assertSessionHasErrors('restriction');
    $this->actingAs(superadmin())->post(route('companies.risk', $tenant), ['restriction' => 'suspended', 'confirmation' => 'Acme'])->assertSessionHasErrors('reason');
    $this->actingAs(support())->post(route('companies.risk', $tenant), [])->assertNotFound();

    expect($tenant->fresh()?->restriction)->toBeNull();
});

it('shows the superadmin the company activity from the audit trail, and support none', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    Context::scope(function () use ($tenant, $other): void {
        resolve(RecordAudit::class)->handle('tenant.updated', $tenant);
        resolve(RecordAudit::class)->handle('tenant.updated', $other);
    });

    $this->actingAs(superadmin())->get(route('companies.show', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('activity.total', 1)->where('activity.data.0.company.id', $tenant->id));
    $this->actingAs(support())->get(route('companies.show', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('activity', null));
});

it('carries no individual answer in its props', function (): void {
    $tenant = Tenant::factory()->create();
    memberOf($tenant, TenantRole::Owner);

    $props = $this->actingAs(superadmin())->get(route('companies.show', $tenant))->inertiaProps();

    expect(individualAnswersIn($props))->toBe([]);
});

it('answers 404 to anyone outside the staff', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->create())->get(route('companies.show', $tenant))->assertNotFound();
});
