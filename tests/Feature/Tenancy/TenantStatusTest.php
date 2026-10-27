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

it('is deactivated even with a future active_until', function (): void {
    $tenant = Tenant::factory()->activeUntil(now()->addMonth())->deactivated()->make();

    expect($tenant->status())->toBe(TenantStatus::Deactivated);
});

it('ends the trial 14 days after it starts', function (): void {
    $startedAt = now()->subDays(3);

    expect(Tenant::factory()->inTrial($startedAt)->make()->trialEndsAt()?->equalTo($startedAt->addDays(14)))->toBeTrue()
        ->and(Tenant::factory()->make()->trialEndsAt())->toBeNull();
});

it('labels each status and says which ones block', function (TenantStatus $status, string $label, bool $blocked): void {
    expect($status->label())->toBe($label)
        ->and($status->isBlocked())->toBe($blocked);
})->with([
    'not started' => [TenantStatus::NotStarted, 'Sin iniciar', false],
    'trial' => [TenantStatus::Trial, 'En prueba', false],
    'active' => [TenantStatus::Active, 'Activa', false],
    'trial ended' => [TenantStatus::TrialEnded, 'Prueba terminada', true],
    'expired' => [TenantStatus::Expired, 'Vencida', true],
    'deactivated' => [TenantStatus::Deactivated, 'Desactivada', true],
]);
