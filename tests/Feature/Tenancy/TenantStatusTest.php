<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;

mutates(Tenant::class, TenantStatus::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-27 12:00:00', 'UTC'));
});

it('is not started until the owner signs in', function (): void {
    expect(Tenant::factory()->make()->status())->toBe(TenantStatus::NotStarted);
});

it('is in trial until the instant 14 days after the owner signs in', function (): void {
    expect(Tenant::factory()->inTrial(now()->subDays(14)->addSecond())->make()->status())->toBe(TenantStatus::Trial)
        ->and(Tenant::factory()->inTrial(now()->subDays(14))->make()->status())->toBe(TenantStatus::TrialEnded)
        ->and(Tenant::factory()->inTrial(now()->subDays(14)->subSecond())->make()->status())->toBe(TenantStatus::TrialEnded);
});

it('is active before active_until and expired from that instant', function (): void {
    expect(Tenant::factory()->activeUntil(now()->addSecond())->make()->status())->toBe(TenantStatus::Active)
        ->and(Tenant::factory()->activeUntil(now())->make()->status())->toBe(TenantStatus::Expired);
});

it('is active over a finished trial', function (): void {
    $tenant = Tenant::factory()->inTrial(now()->subDays(30))->activeUntil(now()->addMonth())->make();

    expect($tenant->status())->toBe(TenantStatus::Active);
});

it('follows its restriction over any date', function (Closure $company, TenantStatus $status): void {
    expect($company()->status())->toBe($status);
})->with([
    'read only' => [fn () => Tenant::factory()->activeUntil(now()->addMonth())->readOnly()->make(), TenantStatus::ReadOnly],
    'suspended' => [fn () => Tenant::factory()->activeUntil(now()->addMonth())->suspended()->make(), TenantStatus::Suspended],
    'archived' => [fn () => Tenant::factory()->inTrial(now())->archived()->make(), TenantStatus::Archived],
]);

it('ends the trial 14 days after it starts', function (): void {
    $startedAt = now()->subDays(3);

    expect(Tenant::factory()->inTrial($startedAt)->make()->trialEndsAt()?->equalTo($startedAt->addDays(14)))->toBeTrue()
        ->and(Tenant::factory()->make()->trialEndsAt())->toBeNull();
});

it('labels each status and says what it leaves of the panel and of the portals, devices and jobs', function (TenantStatus $status, string $label, bool $operates, bool $readOnly, bool $panelBlocked): void {
    expect($status->label())->toBe($label)
        ->and($status->isBlocked())->toBe(! $operates)
        ->and($status->isReadOnly())->toBe($readOnly)
        ->and($status->blocksPanel())->toBe($panelBlocked);
})->with([
    'not started' => [TenantStatus::NotStarted, 'Sin iniciar', true, false, false],
    'trial' => [TenantStatus::Trial, 'En prueba', true, false, false],
    'active' => [TenantStatus::Active, 'Activa', true, false, false],
    // An ended trial and a lapsed term only stop operating: people still read and export. Only a person blocks the panel.
    'trial ended' => [TenantStatus::TrialEnded, 'Prueba terminada', false, true, false],
    'expired' => [TenantStatus::Expired, 'Vencida', false, true, false],
    'read only' => [TenantStatus::ReadOnly, 'Solo lectura', false, true, false],
    'suspended' => [TenantStatus::Suspended, 'Suspendida', false, false, true],
    'archived' => [TenantStatus::Archived, 'Archivada', false, false, true],
]);
