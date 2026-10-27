<?php

declare(strict_types=1);

use App\Console\Commands\GrantSuperadmin;
use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

mutates(GrantSuperadmin::class);

it('grants the superadmin role to an existing user', function (): void {
    $user = User::factory()->create(['email' => 'victor@humetria.mx']);

    $this->artisan('humetria:grant-superadmin', ['email' => 'victor@humetria.mx'])
        ->expectsOutput('victor@humetria.mx ahora es superadmin.')
        ->assertSuccessful();

    expect($user->fresh()?->hasRole(User::SUPERADMIN))->toBeTrue();
});

it('logs and audits the grant, since it changes a role', function (): void {
    Log::spy();
    $user = User::factory()->create(['email' => 'victor@humetria.mx']);

    $this->artisan('humetria:grant-superadmin', ['email' => 'victor@humetria.mx'])->assertSuccessful();

    Log::shouldHaveReceived('info')->once()->with('user.superadmin_granted', ['user_id' => $user->id]);
    expect(AuditEntry::query()->sole())
        ->event->toBe('user.superadmin_granted')
        ->subject_id->toBe((string) $user->id);
});

it('fails without granting anything when no user has that email', function (): void {
    $user = User::factory()->create(['email' => 'victor@humetria.mx']);

    $this->artisan('humetria:grant-superadmin', ['email' => 'otra@humetria.mx'])
        ->expectsOutput('No existe un usuario con el correo otra@humetria.mx.')
        ->assertFailed();

    expect($user->fresh()?->hasRole(User::SUPERADMIN))->toBeFalse()
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('can run twice without duplicating the role', function (): void {
    $user = User::factory()->create(['email' => 'victor@humetria.mx']);

    $this->artisan('humetria:grant-superadmin', ['email' => 'victor@humetria.mx'])->assertSuccessful();
    $this->artisan('humetria:grant-superadmin', ['email' => 'victor@humetria.mx'])->assertSuccessful();

    expect($user->fresh()?->roles()->count())->toBe(1);
});

it('lets a superadmin pass every ability and nobody else', function (): void {
    Gate::define('anything', fn (User $user): bool => false);
    $superadmin = User::factory()->create();
    $superadmin->assignRole(User::SUPERADMIN);

    expect(Gate::forUser($superadmin)->allows('anything'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('anything'))->toBeFalse();
});
