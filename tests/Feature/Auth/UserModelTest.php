<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

mutates(User::class);

it('takes the name, the email and the password from a form, the password hashed', function (): void {
    $user = new User(['name' => 'Rita Campos', 'email' => 'rita@autopartes.mx', 'password' => 'una-contraseña-larga']);

    expect($user->name)->toBe('Rita Campos')
        ->and($user->email)->toBe('rita@autopartes.mx')
        ->and(Hash::check('una-contraseña-larga', $user->password))->toBeTrue();
});

it('never shows the password, the second factor or the remember token when it is written out', function (): void {
    $user = User::factory()->create();
    $user->forceFill(['two_factor_secret' => 'secreto', 'two_factor_recovery_codes' => 'codigos', 'remember_token' => 'recuerdo'])->save();

    expect($user->fresh()?->toArray())->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])
        ->toHaveKeys(['name', 'email']);
});

it('stands for a platform role without asking the database, to ask the gates what the role may do', function (): void {
    $this->expectsDatabaseQueryCount(0);

    $support = User::holdingPlatformRole(User::SUPPORT);
    $superadmin = User::holdingPlatformRole(User::SUPERADMIN);

    expect($support->isSupport())->toBeTrue()
        ->and($support->isSuperadmin())->toBeFalse()
        ->and($support->isStaff())->toBeTrue()
        ->and($superadmin->isSuperadmin())->toBeTrue()
        ->and($superadmin->isSupport())->toBeFalse();
});
