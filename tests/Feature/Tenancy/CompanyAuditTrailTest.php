<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

it('stores the company on every event the superadmin runs on it, so its trail shows them all', function (): void {
    $tenant = Tenant::factory()->activeUntil(CarbonImmutable::parse('2027-12-31', 'UTC'))->create();
    $other = Tenant::factory()->activeUntil(CarbonImmutable::parse('2027-12-31', 'UTC'))->create();
    $admin = superadmin();

    $this->actingAs($admin)->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => 'Falta de pago'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('companies.reactivate', $tenant), ['reason' => 'Ya pagó'])->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('companies.restrict', $other), ['restriction' => 'read_only', 'reason' => 'Otra'])->assertSessionHasNoErrors();

    expect(AuditEntry::query()->whereIn('event', ['tenant.suspended', 'tenant.reactivated'])->where('tenant_id', $tenant->id)->count())->toBe(2)
        ->and(AuditEntry::query()->where('event', 'tenant.set_read_only')->sole()->tenant_id)->toBe($other->id);

    $this->actingAs($admin)->get(route('audit.index', ['company' => $tenant->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 2)
            ->where('entries.data.0.company.id', $tenant->id));
});

it('keeps old entries of a company, saved without it, in its trail', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();
    $row = fn (string $event, array $attributes) => AuditEntry::query()->forceCreate(['id' => (string) Illuminate\Support\Str::uuid(), 'event' => $event, 'metadata' => [], 'created_at' => now(), ...$attributes]);
    $row('tenant.suspended', ['subject_type' => (new Tenant)->getMorphClass(), 'subject_id' => $tenant->id]);
    $row('billing.subscription_set', ['subject_type' => 'subscription', 'subject_id' => 'x', 'metadata' => ['tenant_id' => $tenant->id]]);
    $row('tenant.suspended', ['subject_type' => (new Tenant)->getMorphClass(), 'subject_id' => $other->id]);

    $this->actingAs(superadmin())->get(route('audit.index', ['company' => $tenant->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.total', 2)
            ->where('entries.data.0.company.id', $tenant->id)
            ->where('entries.data.1.company.id', $tenant->id));
});
