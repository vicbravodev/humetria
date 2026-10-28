<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\InvitationStatus;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\TenantInvitation;
use Carbon\CarbonImmutable;

mutates(TenantInvitation::class, InvitationStatus::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-28 12:00:00', 'UTC'));
});

it('is pending until the exact expiry instant', function (): void {
    expect(TenantInvitation::factory()->make(['expires_at' => now()->addSecond()])->status())->toBe(InvitationStatus::Pending)
        ->and(TenantInvitation::factory()->make(['expires_at' => now()])->status())->toBe(InvitationStatus::Expired)
        ->and(TenantInvitation::factory()->make(['expires_at' => now()->subSecond()])->status())->toBe(InvitationStatus::Expired);
});

it('reports accepted and cancelled before looking at the expiry', function (): void {
    $accepted = TenantInvitation::factory()->accepted()->make(['expires_at' => now()->subDay()]);
    $cancelled = TenantInvitation::factory()->cancelled()->make(['expires_at' => now()->addDay()]);

    expect($accepted->status())->toBe(InvitationStatus::Accepted)
        ->and($cancelled->status())->toBe(InvitationStatus::Cancelled);
});

it('reports accepted before cancelled when both dates are set', function (): void {
    $invitation = TenantInvitation::factory()->accepted()->cancelled()->make();

    expect($invitation->status())->toBe(InvitationStatus::Accepted);
});

it('expires 7 days after it is created', function (): void {
    expect(TenantInvitation::VALID_DAYS)->toBe(7)
        ->and(TenantInvitation::factory()->make()->expires_at->equalTo(now()->addDays(7)))->toBeTrue();
});

it('reads its role and dates back from the database as an enum and immutable dates', function (): void {
    $id = TenantInvitation::factory()->accepted()->cancelled()->create()->id;

    $invitation = TenantInvitation::query()->findOrFail($id);

    expect($invitation->role)->toBe(TenantRole::Owner)
        ->and($invitation->expires_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($invitation->accepted_at)->toBeInstanceOf(CarbonImmutable::class)
        ->and($invitation->cancelled_at)->toBeInstanceOf(CarbonImmutable::class);
});

it('hashes the token with sha256', function (): void {
    expect(TenantInvitation::hashToken('abc'))->toBe(hash('sha256', 'abc'));
});

it('labels each status', function (InvitationStatus $status, string $label): void {
    expect($status->label())->toBe($label);
})->with([
    'pending' => [InvitationStatus::Pending, 'Invitación enviada'],
    'expired' => [InvitationStatus::Expired, 'Vencida'],
    'accepted' => [InvitationStatus::Accepted, 'Aceptada'],
    'cancelled' => [InvitationStatus::Cancelled, 'Cancelada'],
]);
