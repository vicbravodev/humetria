<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantInvitation;
use App\Modules\Tenancy\Models\TenantMembership;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('allows a single owner per company', function (): void {
    $tenant = Tenant::factory()->create();
    TenantMembership::factory()->for($tenant)->create();

    expect(fn () => TenantMembership::factory()->for($tenant)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows the same user only once per company', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();
    TenantMembership::factory()->for($tenant)->for($user)->create();

    expect(fn () => TenantMembership::factory()->for($tenant)->for($user)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows a single open invitation per company and role', function (): void {
    $tenant = Tenant::factory()->create();
    TenantInvitation::factory()->for($tenant)->create();

    expect(fn () => TenantInvitation::factory()->for($tenant)->create())
        ->toThrow(UniqueConstraintViolationException::class);
});

it('allows a new open invitation once the previous one is cancelled or accepted', function (): void {
    $tenant = Tenant::factory()->create();
    TenantInvitation::factory()->for($tenant)->cancelled()->create();
    TenantInvitation::factory()->for($tenant)->accepted()->create();

    expect(TenantInvitation::factory()->for($tenant)->create())->toBeInstanceOf(TenantInvitation::class);
});

it('lets two companies each have an open invitation', function (): void {
    TenantInvitation::factory()->for(Tenant::factory()->create())->create();

    expect(TenantInvitation::factory()->for(Tenant::factory()->create())->create())->toBeInstanceOf(TenantInvitation::class);
});

it('rejects an unknown role', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create();

    expect(fn () => DB::table('tenant_memberships')->insert([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'role' => 'boss',
    ]))->toThrow(QueryException::class);
});

it('finds the owner and the open invitation of a company', function (): void {
    $tenant = Tenant::factory()->create();
    $membership = TenantMembership::factory()->for($tenant)->create();
    TenantInvitation::factory()->for($tenant)->cancelled()->create();
    $open = TenantInvitation::factory()->for($tenant)->create();

    expect($tenant->ownerMembership?->is($membership))->toBeTrue()
        ->and($tenant->openInvitation?->is($open))->toBeTrue()
        ->and($tenant->invitations)->toHaveCount(2)
        ->and($membership->role)->toBe(TenantRole::Owner)
        ->and($membership->tenant->is($tenant))->toBeTrue()
        ->and($membership->user)->toBeInstanceOf(User::class)
        ->and($open->tenant->is($tenant))->toBeTrue();
});

it('has no owner or open invitation before one is invited', function (): void {
    $tenant = Tenant::factory()->create();

    expect($tenant->ownerMembership)->toBeNull()
        ->and($tenant->openInvitation)->toBeNull();
});

it('keeps the token only as a unique hash', function (): void {
    $invitation = TenantInvitation::factory()->withToken('secret-token')->create();

    expect($invitation->token_hash)->toBe(hash('sha256', 'secret-token'))
        ->and(TenantInvitation::query()->open()->count())->toBe(1);
});
