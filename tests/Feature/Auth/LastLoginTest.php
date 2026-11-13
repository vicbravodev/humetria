<?php

declare(strict_types=1);

use App\Listeners\RecordLastLogin;
use App\Models\User;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Hash;

mutates(RecordLastLogin::class);

it('records when a person signs in', function (): void {
    $this->travelTo('2026-11-12 18:00 UTC');
    $user = User::factory()->create(['password' => Hash::make('una-clave-segura-1')]);

    expect($user->last_login_at)->toBeNull();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'una-clave-segura-1']);

    expect($user->fresh()->last_login_at->toIso8601String())->toBe('2026-11-12T18:00:00+00:00');
});

it('keeps the last login of a person who fails to sign in', function (): void {
    $user = User::factory()->create(['password' => Hash::make('una-clave-segura-1')]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'otra-cosa']);

    expect($user->fresh()->last_login_at)->toBeNull();
});

it('does not count the superadmin entering as the owner as the owner signing in, nor coming back as the superadmin signing in', function (): void {
    $superadmin = superadmin();
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);

    $this->actingAs($superadmin)->post(route('companies.impersonate', $tenant));

    expect($owner->fresh()->last_login_at)->toBeNull();

    $this->post(route('impersonation.stop'));

    expect($owner->fresh()->last_login_at)->toBeNull()
        ->and($superadmin->fresh()->last_login_at)->toBeNull();
});
