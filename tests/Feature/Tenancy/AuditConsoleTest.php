<?php

declare(strict_types=1);

use App\Http\Middleware\ForbidWhileImpersonating;
use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\AuditPages;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(AuditPages::class);

/** One audit row, written straight to the table: its time and who did it are the point of each test. */
function auditRow(string $event, array $attributes = []): AuditEntry
{
    return AuditEntry::query()->forceCreate([
        'id' => (string) Illuminate\Support\Str::uuid(),
        'event' => $event,
        'metadata' => [],
        'created_at' => now(),
        ...$attributes,
    ]);
}

it('lives at /admin/audit', function (): void {
    expect(route('audit.index', absolute: false))->toBe('/admin/audit');
});

it('sends guests to the login', function (): void {
    $this->get(route('audit.index'))->assertRedirect(route('login'));
});

it('answers 403 to support and to an owner: only the superadmin reads the audit trail', function (User $user): void {
    auditRow('tenant.created');

    $this->actingAs($user)->get(route('audit.index'))->assertForbidden();
})->with([
    'support' => fn () => support(),
    'someone with no role' => fn () => User::factory()->create(),
]);

it('lists the entries newest first, 50 to a page, with who acted and in which company', function (): void {
    $this->travelTo('2026-11-12 18:00 UTC');
    $tenant = Tenant::factory()->create(['trade_name' => 'Acme Demo']);
    $actor = User::factory()->create(['name' => 'Ana López', 'email' => 'ana@acme.mx']);
    auditRow('campaign.launched', ['tenant_id' => $tenant->id, 'actor_id' => $actor->id, 'metadata' => ['work_center' => 'Planta', 'size' => 12], 'created_at' => now()->subHour()]);
    auditRow('tenant.created', ['created_at' => now()->subDay()]);

    $this->actingAs(superadmin())->get(route('audit.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/audit')
            ->where('entries.total', 2)
            ->where('entries.per_page', 50)
            ->where('entries.data.0.event', 'campaign.launched')
            ->where('entries.data.0.at', '12 nov 2026, 11:00')
            ->where('entries.data.0.actor', ['name' => 'Ana López', 'email' => 'ana@acme.mx'])
            ->where('entries.data.0.impersonator', null)
            ->where('entries.data.0.company', ['id' => $tenant->id, 'name' => 'Acme Demo'])
            ->where('entries.data.0.context', ['work_center' => 'Planta', 'size' => '12'])
            ->where('entries.data.1.event', 'tenant.created')
            ->where('entries.data.1.actor', null)
            ->where('entries.data.1.company', null));
});

it('pages after fifty entries', function (): void {
    foreach (range(1, 51) as $minute) {
        auditRow('tenant.updated', ['created_at' => now()->subMinutes($minute)]);
    }

    $this->actingAs(superadmin())->get(route('audit.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->where('entries.current_page', 2)->has('entries.data', 1));
});

it('filters by family of event, by prefix and not by what merely contains it', function (): void {
    auditRow('billing.payment_recorded');
    auditRow('tenant.created');
    auditRow('tenant_member.removed');

    $this->actingAs(superadmin())->get(route('audit.index', ['family' => 'tenant']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 1)
            ->where('entries.data.0.event', 'tenant.created')
            ->where('filters.family', 'tenant'));
});

it('ignores a family that is not in the catalog', function (): void {
    auditRow('tenant.created');
    auditRow('billing.payment_recorded');

    $this->actingAs(superadmin())->get(route('audit.index', ['family' => 'x%']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 2)->where('filters.family', ''));
});

it('filters by company', function (): void {
    $acme = Tenant::factory()->create();
    $beta = Tenant::factory()->create();
    auditRow('tenant.updated', ['tenant_id' => $acme->id]);
    auditRow('tenant.updated', ['tenant_id' => $beta->id]);
    auditRow('tenant.updated');

    $this->actingAs(superadmin())->get(route('audit.index', ['company' => $acme->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 1)
            ->where('entries.data.0.company.id', $acme->id));
});

it('filters by a range of local days, both edges included', function (): void {
    // Mexico City is UTC-6 in November: the 10th local runs from 06:00 UTC to 05:59:59 UTC the next day.
    auditRow('tenant.updated', ['metadata' => ['mark' => 'before'], 'created_at' => '2026-11-10 05:59:59+00']);
    auditRow('tenant.updated', ['metadata' => ['mark' => 'first'], 'created_at' => '2026-11-10 06:00:00+00']);
    auditRow('tenant.updated', ['metadata' => ['mark' => 'last'], 'created_at' => '2026-11-12 05:59:59+00']);
    auditRow('tenant.updated', ['metadata' => ['mark' => 'after'], 'created_at' => '2026-11-12 06:00:00+00']);

    $this->actingAs(superadmin())->get(route('audit.index', ['from' => '2026-11-10', 'to' => '2026-11-11']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 2)
            ->where('filters.from', '2026-11-10')
            ->where('filters.to', '2026-11-11'));
});

it('ignores a date that is not a date', function (): void {
    auditRow('tenant.updated');

    $this->actingAs(superadmin())->get(route('audit.index', ['from' => 'ayer', 'to' => '2026-13-45']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 1)->where('filters.from', '')->where('filters.to', ''));
});

it('filters by the email of whoever acted', function (): void {
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    $luis = User::factory()->create(['email' => 'luis@beta.mx']);
    auditRow('tenant.updated', ['actor_id' => $ana->id]);
    auditRow('tenant.updated', ['actor_id' => $luis->id]);

    $this->actingAs(superadmin())->get(route('audit.index', ['q' => 'ANA@']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 1)
            ->where('entries.data.0.actor.email', 'ana@acme.mx'));
});

it('searches the email as text, not as a pattern', function (): void {
    $ana = User::factory()->create(['email' => 'ana@acme.mx']);
    auditRow('tenant.updated', ['actor_id' => $ana->id]);

    $this->actingAs(superadmin())->get(route('audit.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->where('entries.total', 0));
});

it('shows who was impersonating on an entry made while impersonating', function (): void {
    $owner = User::factory()->create(['name' => 'Dueña', 'email' => 'duena@acme.mx']);
    $staff = User::factory()->create(['name' => 'Soporte Uno', 'email' => 'uno@humetria.mx']);
    auditRow('campaign.launched', ['actor_id' => $owner->id, 'impersonator_id' => $staff->id]);

    $this->actingAs(superadmin())->get(route('audit.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.data.0.actor.email', 'duena@acme.mx')
            ->where('entries.data.0.impersonator', ['name' => 'Soporte Uno', 'email' => 'uno@humetria.mx']));
});

it('reads a page in the same number of queries however many entries it holds', function (): void {
    $this->actingAs(superadmin());
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('audit.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    $count(); // the first request also fills the permission cache
    foreach (range(1, 2) as $n) {
        auditRow('tenant.updated', ['actor_id' => User::factory()->create()->id, 'tenant_id' => Tenant::factory()->create()->id, 'impersonator_id' => User::factory()->create()->id]);
    }
    $few = $count();
    foreach (range(1, 12) as $n) {
        auditRow('tenant.updated', ['actor_id' => User::factory()->create()->id, 'tenant_id' => Tenant::factory()->create()->id, 'impersonator_id' => User::factory()->create()->id]);
    }

    expect($count())->toBe($few);
});

it('has no route that changes or deletes an entry', function (): void {
    $writes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route): bool => str_starts_with($route->uri(), 'admin/audit') && array_diff($route->methods(), ['GET', 'HEAD']) !== []);

    expect($writes)->toBeEmpty();
});

it('is forbidden while impersonating', function (): void {
    expect(ForbidWhileImpersonating::guards('audit.index'))->toBeTrue();
});

it('offers the trail of a company from its page to the superadmin only', function (User $user, bool $offered): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs($user)->get(route('companies.show', $tenant))
        ->assertInertia(fn (Assert $page) => $page->where('can_audit', $offered));
})->with([
    'superadmin' => [fn () => superadmin(), true],
    'support' => [fn () => support(), false],
]);
