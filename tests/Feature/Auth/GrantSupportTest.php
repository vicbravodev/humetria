<?php

declare(strict_types=1);

use App\Console\Commands\GrantSupport;
use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Support\Facades\Log;

mutates(GrantSupport::class);

it('gives the support role to an existing user, found by email without regard to case or spaces', function (): void {
    $user = User::factory()->create(['email' => 'ana@humetria.mx']);

    $this->artisan('humetria:grant-support', ['email' => '  Ana@Humetria.MX '])
        ->expectsOutput('ana@humetria.mx ahora es soporte.')
        ->assertSuccessful();

    expect($user->refresh()->isSupport())->toBeTrue()
        ->and($user->isSuperadmin())->toBeFalse();
});

it('logs and audits it, since it gives a role', function (): void {
    Log::spy();
    $user = User::factory()->create(['email' => 'ana@humetria.mx']);

    $this->artisan('humetria:grant-support', ['email' => 'ana@humetria.mx'])->assertSuccessful();

    Log::shouldHaveReceived('info')->once()->with('user.support_granted', ['user_id' => $user->id]);
    expect(AuditEntry::query()->sole())->event->toBe('user.support_granted')->subject_id->toBe((string) $user->id);
});

it('fails for an email nobody has, and creates nobody', function (): void {
    $this->artisan('humetria:grant-support', ['email' => 'nadie@humetria.mx'])
        ->expectsOutput('No hay ningún usuario con el correo nadie@humetria.mx.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0)->and(AuditEntry::query()->count())->toBe(0);
});

it('does nothing, and says so, for someone who already is support', function (): void {
    $support = support();

    $this->artisan('humetria:grant-support', ['email' => $support->email])
        ->expectsOutput("{$support->email} ya es soporte.")
        ->assertSuccessful();

    expect(AuditEntry::query()->count())->toBe(0);
});

it('refuses to turn a superadmin into support', function (): void {
    $superadmin = superadmin();

    $this->artisan('humetria:grant-support', ['email' => $superadmin->email])
        ->expectsOutput("{$superadmin->email} es superadmin: no se le da el rol de soporte.")
        ->assertFailed();

    expect($superadmin->refresh()->isSupport())->toBeFalse();
});
