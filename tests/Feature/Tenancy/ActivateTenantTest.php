<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\ActivateTenant;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Http\Requests\ActivateTenantRequest;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(ActivateTenant::class, ActivateTenantRequest::class);

beforeEach(fn () => $this->travelTo('2026-10-27 18:00 UTC'));

it('lives at /admin/companies/{tenant}/activate', function (): void {
    expect(route('companies.activate', 'abc', absolute: false))->toBe('/admin/companies/abc/activate');
});

it('sends guests to the login', function (): void {
    $tenant = Tenant::factory()->create();

    $this->post(route('companies.activate', $tenant), ['active_until' => '2026-12-31'])->assertRedirect(route('login'));

    expect($tenant->refresh()->active_until)->toBeNull();
});

it('activates a company until the end of the chosen day in Mexico City', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.activate', $tenant), ['active_until' => '2026-12-31'])
        ->assertRedirect(route('companies.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empresa activa hasta 31 dic 2026.']]);

    $tenant->refresh();

    // The column keeps whole seconds, so the last instant stored for the day is 23:59:59 Mexico City.
    expect($tenant->active_until?->format('Y-m-d H:i:s.u'))->toBe('2027-01-01 05:59:59.000000')
        ->and($tenant->status())->toBe(TenantStatus::Active);
});

it('measures today in Mexico City time', function (): void {
    // 27 Oct, 21:00 in Mexico City, but already the 28th in UTC.
    $this->travelTo('2026-10-28 03:00 UTC');
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.activate', $tenant), ['active_until' => '2026-10-27'])
        ->assertSessionHasErrors('active_until');

    expect($tenant->refresh()->active_until)->toBeNull();

    $this->actingAs(superadmin())
        ->post(route('companies.activate', $tenant), ['active_until' => '2026-10-28'])
        ->assertSessionHasNoErrors();

    expect($tenant->refresh()->active_until)->not->toBeNull();
});

it('rejects today, past dates and malformed dates', function (?string $value, string $error): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.activate', $tenant), $value === null ? [] : ['active_until' => $value])
        ->assertSessionHasErrors(['active_until' => $error]);

    expect($tenant->refresh()->active_until)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
})->with([
    'missing' => [null, 'El campo fecha de vigencia es obligatorio.'],
    'today' => ['2026-10-27', 'Elige una fecha a partir de mañana.'],
    'past' => ['2026-10-01', 'Elige una fecha a partir de mañana.'],
    'malformed' => ['31/12/2026', 'El campo fecha de vigencia debe tener el formato Y-m-d.'],
    'not a date' => ['mañana', 'El campo fecha de vigencia debe tener el formato Y-m-d.'],
]);

it('asks for a date from tomorrow on in plain words, without the raw date', function (): void {
    $this->actingAs(superadmin())
        ->post(route('companies.activate', Tenant::factory()->create()), ['active_until' => '2026-10-27'])
        ->assertSessionHasErrors(['active_until' => 'Elige una fecha a partir de mañana.']);
});

it('names one problem at a time for a malformed date', function (): void {
    $this->actingAs(superadmin())
        ->post(route('companies.activate', Tenant::factory()->create()), ['active_until' => '31/12/2026'])
        ->assertSessionHasErrors(['active_until' => 'El campo fecha de vigencia debe tener el formato Y-m-d.']);

    expect(session('errors')->get('active_until'))->toHaveCount(1);
});

it('accepts tomorrow', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.activate', $tenant), ['active_until' => '2026-10-28'])
        ->assertSessionHasNoErrors();

    expect($tenant->refresh()->active_until?->toIso8601String())->toBe('2026-10-29T05:59:59+00:00');
});

it('reactivates a deactivated, expired or finished-trial company', function (Closure $company): void {
    $tenant = $company()->create();

    expect($tenant->status()->isBlocked())->toBeTrue();

    $this->actingAs(superadmin())
        ->post(route('companies.activate', $tenant), ['active_until' => '2026-12-31'])
        ->assertSessionHasNoErrors();

    $tenant->refresh();

    expect($tenant->status())->toBe(TenantStatus::Active)
        ->and($tenant->deactivated_at)->toBeNull();
})->with([
    'deactivated' => [fn () => Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-12-01', 'UTC'))->deactivated()],
    'expired' => [fn () => Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-09-30', 'UTC'))],
    'trial ended' => [fn () => Tenant::factory()->inTrial(CarbonImmutable::parse('2026-10-01 18:00', 'UTC'))],
]);

it('activates a company whose trial never started, without starting it', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())->post(route('companies.activate', $tenant), ['active_until' => '2026-12-31']);

    expect($tenant->refresh()->trial_started_at)->toBeNull();
});

it('keeps the trial dates of a company that was in trial', function (): void {
    $started = CarbonImmutable::parse('2026-10-20 18:00', 'UTC');
    $tenant = Tenant::factory()->inTrial($started)->create();

    $this->actingAs(superadmin())->post(route('companies.activate', $tenant), ['active_until' => '2026-12-31']);

    expect($tenant->refresh()->trial_started_at?->equalTo($started))->toBeTrue();
});

it('logs and audits the new end date', function (): void {
    $tenant = Tenant::factory()->create();
    Log::spy();

    $this->actingAs(superadmin())->post(route('companies.activate', $tenant), ['active_until' => '2026-12-31']);

    Log::shouldHaveReceived('info')->once()->with('tenant.activated', ['tenant_id' => $tenant->id, 'active_until' => '2027-01-01T05:59:59+00:00']);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('tenant.activated')
        ->and($entry->subject_id)->toBe($tenant->id)
        ->and($entry->metadata)->toBe(['active_until' => '2027-01-01T05:59:59+00:00']);
});

it('answers 404 to a non-superadmin and to an unknown company', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('companies.activate', $tenant), ['active_until' => '2026-12-31'])
        ->assertNotFound();

    $this->actingAs(superadmin())
        ->post(route('companies.activate', '0198c3a2-0000-4000-8000-000000000000'), ['active_until' => '2026-12-31'])
        ->assertNotFound();

    expect($tenant->refresh()->active_until)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('sends the console tomorrow in Mexico City time as the earliest activation date', function (): void {
    // 27 Oct, 21:00 in Mexico City: tomorrow there is the 28th, not the 29th.
    $this->travelTo('2026-10-28 03:00 UTC');

    $this->actingAs(superadmin())
        ->get(route('companies.index'))
        ->assertInertia(fn (Assert $page) => $page->where('activationMinDate', '2026-10-28'));
});
