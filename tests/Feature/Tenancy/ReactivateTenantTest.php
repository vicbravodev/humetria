<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\ReactivateTenant;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Http\Requests\ReactivateTenantRequest;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(ReactivateTenant::class, ReactivateTenantRequest::class);

beforeEach(fn () => $this->travelTo('2026-10-27 18:00 UTC'));

it('lives at /admin/companies/{tenant}/reactivate', function (): void {
    expect(route('companies.reactivate', 'abc', absolute: false))->toBe('/admin/companies/abc/reactivate');
});

it('sends guests to the login', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

    $this->post(route('companies.reactivate', $tenant), ['reason' => 'Pagó'])->assertRedirect(route('login'));

    expect($tenant->refresh()->restriction)->not->toBeNull();
});

it('lifts any restriction and clears its reason and moment', function (Closure $company): void {
    $tenant = $company()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.reactivate', $tenant), ['reason' => 'Regularizó su pago'])
        ->assertRedirect(route('companies.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empresa reactivada.']]);

    $tenant->refresh();

    expect($tenant->restriction)->toBeNull()
        ->and($tenant->restriction_reason)->toBeNull()
        ->and($tenant->restricted_at)->toBeNull();
})->with([
    'read only' => [fn () => Tenant::factory()->readOnly()],
    'suspended' => [fn () => Tenant::factory()->suspended()],
    'archived' => [fn () => Tenant::factory()->archived()],
]);

it('never resets the trial nor the term: the company comes back to the status its dates give', function (): void {
    $trialStart = CarbonImmutable::parse('2026-09-01 18:00', 'UTC');
    $trial = Tenant::factory()->inTrial($trialStart)->suspended()->create();
    $paid = Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-12-31', 'UTC'))->suspended()->create();

    $this->actingAs(superadmin())->post(route('companies.reactivate', $trial), ['reason' => 'Error de captura']);
    $this->actingAs(superadmin())->post(route('companies.reactivate', $paid), ['reason' => 'Error de captura']);

    expect($trial->refresh()->trial_started_at?->toIso8601String())->toBe($trialStart->toIso8601String())
        ->and($trial->active_until)->toBeNull()
        ->and($trial->status())->toBe(TenantStatus::TrialEnded)
        ->and($paid->refresh()->active_until?->toIso8601String())->toBe('2026-12-31T00:00:00+00:00')
        ->and($paid->status())->toBe(TenantStatus::Active);
});

it('refuses to reactivate a company that has no restriction', function (): void {
    $tenant = Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-12-31', 'UTC'))->create();
    Log::spy();

    $this->actingAs(superadmin())
        ->post(route('companies.reactivate', $tenant), ['reason' => 'Nada'])
        ->assertStatus(409);

    expect(AuditEntry::query()->count())->toBe(0);
    Log::shouldNotHaveReceived('info');
});

it('demands a reason of at most 500 characters, and changes nothing without it', function (array $body): void {
    $tenant = Tenant::factory()->suspended()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.reactivate', $tenant), $body)
        ->assertSessionHasErrors('reason');

    expect($tenant->refresh()->restriction)->not->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
})->with([
    'none' => [[]],
    'blank' => [['reason' => ' ']],
    'long' => [['reason' => str_repeat('a', 501)]],
]);

it('logs and audits the reactivation with its reason and what the company was', function (): void {
    $tenant = Tenant::factory()->archived()->create();
    Log::spy();

    $this->actingAs(superadmin())->post(route('companies.reactivate', $tenant), ['reason' => 'Volvió a contratar']);

    Log::shouldHaveReceived('info')->once()->with('tenant.reactivated', ['tenant_id' => $tenant->id, 'previous' => 'archived']);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('tenant.reactivated')
        ->and($entry->subject_id)->toBe($tenant->id)
        ->and($entry->metadata)->toBe(['reason' => 'Volvió a contratar', 'previous' => 'archived']);
});

it('answers 404 to a non-superadmin and to an unknown company', function (): void {
    $tenant = Tenant::factory()->suspended()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('companies.reactivate', $tenant), ['reason' => 'Pagó'])
        ->assertNotFound();

    $this->actingAs(superadmin())
        ->post(route('companies.reactivate', '0198c3a2-0000-4000-8000-000000000000'), ['reason' => 'Pagó'])
        ->assertNotFound();

    expect($tenant->refresh()->restriction)->not->toBeNull();
});
