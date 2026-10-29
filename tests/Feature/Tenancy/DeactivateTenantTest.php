<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\DeactivateTenant;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(DeactivateTenant::class);

beforeEach(fn () => $this->travelTo('2026-10-27 18:00 UTC'));

it('lives at /admin/companies/{tenant}/deactivate', function (): void {
    expect(route('companies.deactivate', 'abc', absolute: false))->toBe('/admin/companies/abc/deactivate');
});

it('sends guests to the login', function (): void {
    $tenant = Tenant::factory()->create();

    $this->post(route('companies.deactivate', $tenant))->assertRedirect(route('login'));

    expect($tenant->refresh()->deactivated_at)->toBeNull();
});

it('deactivates a company that is not started, in trial or active', function (Closure $company): void {
    $tenant = $company()->create();

    expect($tenant->status()->isBlocked())->toBeFalse();

    $this->actingAs(superadmin())
        ->post(route('companies.deactivate', $tenant))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empresa desactivada.']]);

    $tenant->refresh();

    expect($tenant->status())->toBe(TenantStatus::Deactivated)
        ->and($tenant->deactivated_at?->toIso8601String())->toBe('2026-10-27T18:00:00+00:00');
})->with([
    'not started' => [fn () => Tenant::factory()],
    'in trial' => [fn () => Tenant::factory()->inTrial(CarbonImmutable::parse('2026-10-20 18:00', 'UTC'))],
    'active' => [fn () => Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-12-31', 'UTC'))],
]);

it('refuses to deactivate a company that is already blocked', function (Closure $company): void {
    $tenant = $company()->create();
    $before = $tenant->deactivated_at;
    Log::spy();

    expect($tenant->status()->isBlocked())->toBeTrue();

    $this->actingAs(superadmin())
        ->post(route('companies.deactivate', $tenant))
        ->assertStatus(409);

    expect($tenant->refresh()->deactivated_at?->toIso8601String())->toBe($before?->toIso8601String())
        ->and(AuditEntry::query()->count())->toBe(0);

    Log::shouldNotHaveReceived('info');
})->with([
    'deactivated' => [fn () => Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-12-01', 'UTC'))->deactivated()],
    'expired' => [fn () => Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-09-30', 'UTC'))],
    'trial ended' => [fn () => Tenant::factory()->inTrial(CarbonImmutable::parse('2026-10-01 18:00', 'UTC'))],
]);

it('keeps the company and its data', function (): void {
    $tenant = Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-12-31', 'UTC'))->create();
    $before = $tenant->only(['legal_name', 'trade_name', 'rfc', 'slug', 'trial_started_at', 'active_until']);

    $this->actingAs(superadmin())->post(route('companies.deactivate', $tenant));

    expect(Tenant::query()->count())->toBe(1)
        ->and($tenant->refresh()->only(['legal_name', 'trade_name', 'rfc', 'slug', 'trial_started_at', 'active_until']))->toEqual($before);
});

it('logs and audits the deactivation', function (): void {
    $tenant = Tenant::factory()->create();
    Log::spy();

    $this->actingAs(superadmin())->post(route('companies.deactivate', $tenant));

    Log::shouldHaveReceived('info')->once()->with('tenant.deactivated', ['tenant_id' => $tenant->id]);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('tenant.deactivated')
        ->and($entry->subject_id)->toBe($tenant->id)
        ->and($entry->tenant_id)->toBeNull();
});

it('answers 404 to a non-superadmin and to an unknown company', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('companies.deactivate', $tenant))
        ->assertNotFound();

    $this->actingAs(superadmin())
        ->post(route('companies.deactivate', '0198c3a2-0000-4000-8000-000000000000'))
        ->assertNotFound();

    expect($tenant->refresh()->deactivated_at)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
});
