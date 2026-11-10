<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\RestrictTenant;
use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Http\Requests\RestrictTenantRequest;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(RestrictTenant::class, RestrictTenantRequest::class, TenantRestriction::class);

beforeEach(fn () => $this->travelTo('2026-10-27 18:00 UTC'));

it('lives at /admin/companies/{tenant}/restriction', function (): void {
    expect(route('companies.restrict', 'abc', absolute: false))->toBe('/admin/companies/abc/restriction');
});

it('sends guests to the login', function (): void {
    $tenant = Tenant::factory()->create();

    $this->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => 'Falta de pago'])->assertRedirect(route('login'));

    expect($tenant->refresh()->restriction)->toBeNull();
});

it('puts a company in read-only, suspended or archived with the reason and the moment', function (string $restriction, TenantStatus $status, string $toast): void {
    $tenant = Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-12-31', 'UTC'))->create();

    $this->actingAs(superadmin())
        ->post(route('companies.restrict', $tenant), ['restriction' => $restriction, 'reason' => 'Falta de pago de octubre'])
        ->assertRedirect(route('companies.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => $toast]]);

    $tenant->refresh();

    expect($tenant->status())->toBe($status)
        ->and($tenant->restriction?->value)->toBe($restriction)
        ->and($tenant->restriction_reason)->toBe('Falta de pago de octubre')
        ->and($tenant->restricted_at?->toIso8601String())->toBe('2026-10-27T18:00:00+00:00');
})->with([
    'read only' => ['read_only', TenantStatus::ReadOnly, 'Empresa en solo lectura.'],
    'suspended' => ['suspended', TenantStatus::Suspended, 'Empresa suspendida.'],
    'archived' => ['archived', TenantStatus::Archived, 'Empresa archivada.'],
]);

it('restricts a company in any state, an expired one included', function (Closure $company): void {
    $tenant = $company()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => 'Contrato terminado'])
        ->assertRedirect(route('companies.index'));

    expect($tenant->refresh()->status())->toBe(TenantStatus::Suspended);
})->with([
    'not started' => [fn () => Tenant::factory()],
    'in trial' => [fn () => Tenant::factory()->inTrial(CarbonImmutable::parse('2026-10-20 18:00', 'UTC'))],
    'expired' => [fn () => Tenant::factory()->activeUntil(CarbonImmutable::parse('2026-09-30', 'UTC'))],
    'read only' => [fn () => Tenant::factory()->readOnly()],
]);

it('replaces the reason and the moment when a restricted company moves to another restriction', function (): void {
    $tenant = Tenant::factory()->readOnly('Pago atrasado')->create();
    $this->travelTo('2026-11-15 10:00 UTC');

    $this->actingAs(superadmin())
        ->post(route('companies.restrict', $tenant), ['restriction' => 'archived', 'reason' => 'Dejó de ser cliente'])
        ->assertRedirect(route('companies.index'));

    $tenant->refresh();

    expect($tenant->restriction)->toBe(TenantRestriction::Archived)
        ->and($tenant->restriction_reason)->toBe('Dejó de ser cliente')
        ->and($tenant->restricted_at?->toIso8601String())->toBe('2026-11-15T10:00:00+00:00');
});

it('refuses to apply the restriction the company already has', function (string $restriction): void {
    $tenant = Tenant::factory()->create();
    $tenant->forceFill(['restriction' => $restriction, 'restriction_reason' => 'Original', 'restricted_at' => '2026-10-01 12:00:00+00'])->save();
    Log::spy();

    $this->actingAs(superadmin())
        ->post(route('companies.restrict', $tenant), ['restriction' => $restriction, 'reason' => 'Otra vez'])
        ->assertStatus(409);

    expect($tenant->refresh()->restriction_reason)->toBe('Original')
        ->and(AuditEntry::query()->count())->toBe(0);

    Log::shouldNotHaveReceived('info');
})->with(['read_only', 'suspended', 'archived']);

it('demands a reason of at most 500 characters and a restriction it knows, and changes nothing otherwise', function (array $body, string $field): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.restrict', $tenant), $body)
        ->assertSessionHasErrors($field);

    expect($tenant->refresh()->restriction)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
})->with([
    'no reason' => [['restriction' => 'suspended'], 'reason'],
    'blank reason' => [['restriction' => 'suspended', 'reason' => '   '], 'reason'],
    'long reason' => [['restriction' => 'suspended', 'reason' => str_repeat('a', 501)], 'reason'],
    'no restriction' => [['reason' => 'Falta de pago'], 'restriction'],
    'unknown restriction' => [['restriction' => 'frozen', 'reason' => 'Falta de pago'], 'restriction'],
]);

it('accepts a reason of exactly 500 characters', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(superadmin())
        ->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => str_repeat('a', 500)])
        ->assertSessionHasNoErrors();

    expect($tenant->refresh()->restriction)->toBe(TenantRestriction::Suspended);
});

it('keeps the company, its dates and its data: nothing is deleted and the trial is not touched', function (): void {
    $tenant = Tenant::factory()->inTrial(CarbonImmutable::parse('2026-10-20 18:00', 'UTC'))->activeUntil(CarbonImmutable::parse('2026-12-31', 'UTC'))->create();
    $before = $tenant->only(['legal_name', 'trade_name', 'rfc', 'slug', 'trial_started_at', 'active_until']);

    $this->actingAs(superadmin())->post(route('companies.restrict', $tenant), ['restriction' => 'archived', 'reason' => 'Baja']);

    expect(Tenant::query()->count())->toBe(1)
        ->and($tenant->refresh()->only(['legal_name', 'trade_name', 'rfc', 'slug', 'trial_started_at', 'active_until']))->toEqual($before);
});

it('logs and audits each restriction with its reason', function (string $restriction, string $event): void {
    $tenant = Tenant::factory()->create();
    Log::spy();

    $this->actingAs(superadmin())->post(route('companies.restrict', $tenant), ['restriction' => $restriction, 'reason' => 'Falta de pago']);

    Log::shouldHaveReceived('info')->once()->with($event, ['tenant_id' => $tenant->id, 'previous' => null]);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe($event)
        ->and($entry->subject_id)->toBe($tenant->id)
        ->and($entry->tenant_id)->toBeNull()
        ->and($entry->metadata)->toBe(['reason' => 'Falta de pago', 'previous' => null]);
})->with([
    'read only' => ['read_only', 'tenant.set_read_only'],
    'suspended' => ['suspended', 'tenant.suspended'],
    'archived' => ['archived', 'tenant.archived'],
]);

it('records what the company was before in the log and the audit trail', function (): void {
    $tenant = Tenant::factory()->readOnly()->create();
    Log::spy();

    $this->actingAs(superadmin())->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => 'Escalada']);

    Log::shouldHaveReceived('info')->once()->with('tenant.suspended', ['tenant_id' => $tenant->id, 'previous' => 'read_only']);
    expect(AuditEntry::query()->sole()->metadata)->toBe(['reason' => 'Escalada', 'previous' => 'read_only']);
});

it('answers 404 to a non-superadmin and to an unknown company', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('companies.restrict', $tenant), ['restriction' => 'suspended', 'reason' => 'Falta de pago'])
        ->assertNotFound();

    $this->actingAs(superadmin())
        ->post(route('companies.restrict', '0198c3a2-0000-4000-8000-000000000000'), ['restriction' => 'suspended', 'reason' => 'Falta de pago'])
        ->assertNotFound();

    expect($tenant->refresh()->restriction)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
});
