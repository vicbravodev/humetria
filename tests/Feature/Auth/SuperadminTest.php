<?php

declare(strict_types=1);

use App\Console\Commands\CreateSuperadmin;
use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;

mutates(CreateSuperadmin::class);

/**
 * Runs the command answering its four questions.
 */
function createSuperadmin(string $name = 'Victor Bravo', string $email = 'victor@humetria.mx', string $password = 'contraseña-segura', ?string $confirmation = null): PendingCommand
{
    return test()->artisan('humetria:create-superadmin')
        ->expectsQuestion('Nombre', $name)
        ->expectsQuestion('Correo', $email)
        ->expectsQuestion('Contraseña', $password)
        ->expectsQuestion('Confirma la contraseña', $confirmation ?? $password);
}

it('creates the first superadmin with a verified email, even when other users exist', function (): void {
    User::factory()->create();

    createSuperadmin()
        ->expectsOutput('victor@humetria.mx ahora es superadmin.')
        ->assertSuccessful();

    $superadmin = User::query()->where('email', 'victor@humetria.mx')->sole();

    expect($superadmin->name)->toBe('Victor Bravo')
        ->and(Hash::check('contraseña-segura', $superadmin->password))->toBeTrue()
        ->and($superadmin->email_verified_at)->not->toBeNull()
        ->and($superadmin->hasRole(User::SUPERADMIN))->toBeTrue();
});

it('stores the name trimmed and the email trimmed and in lowercase, as Fortify compares it at login', function (): void {
    createSuperadmin(name: '  Victor Bravo ', email: '  Victor@Humetria.MX ')->assertSuccessful();

    expect(User::query()->sole())
        ->name->toBe('Victor Bravo')
        ->email->toBe('victor@humetria.mx');
});

it('logs and audits the creation, since it gives a role', function (): void {
    Log::spy();

    createSuperadmin()->assertSuccessful();

    $superadmin = User::query()->sole();
    Log::shouldHaveReceived('info')->once()->with('user.superadmin_created', ['user_id' => $superadmin->id]);
    expect(AuditEntry::query()->sole())
        ->event->toBe('user.superadmin_created')
        ->subject_id->toBe((string) $superadmin->id);
});

it('refuses without asking anything when a superadmin already exists', function (): void {
    User::factory()->create()->assignRole(User::SUPERADMIN);

    $this->artisan('humetria:create-superadmin')
        ->expectsOutput('Ya existe un superadmin. Los siguientes se crean desde la aplicación.')
        ->assertFailed();

    expect(User::query()->count())->toBe(1)
        ->and(AuditEntry::query()->count())->toBe(0);
});

it('rejects invalid answers without creating anyone', function (array $answers, string $error): void {
    User::factory()->create(['email' => 'ocupado@humetria.mx']);

    createSuperadmin(...$answers)
        ->expectsOutput($error)
        ->assertFailed();

    expect(User::query()->count())->toBe(1)
        ->and(AuditEntry::query()->count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'El campo nombre es obligatorio.'],
    'name too long' => [['name' => str_repeat('a', 256)], 'El campo nombre no debe tener más de 255 caracteres.'],
    'missing email' => [['email' => ''], 'El campo correo electrónico es obligatorio.'],
    'invalid email' => [['email' => 'victor'], 'El campo correo electrónico debe ser un correo electrónico válido.'],
    'email too long' => [['email' => str_repeat('a', 64).'@'.str_repeat('b', 63).'.'.str_repeat('c', 63).'.'.str_repeat('d', 63).'.mx'], 'El campo correo electrónico no debe tener más de 255 caracteres.'],
    'email in use' => [['email' => 'Ocupado@humetria.mx'], 'El correo electrónico ya está en uso.'],
    'short password' => [['password' => 'corta'], 'El campo contraseña debe tener al menos 8 caracteres.'],
    'mismatched confirmation' => [['confirmation' => 'otra-contraseña'], 'La confirmación del campo contraseña no coincide.'],
]);

it('lets a superadmin pass every ability and nobody else', function (): void {
    Gate::define('anything', fn (User $user): bool => false);
    $superadmin = User::factory()->create();
    $superadmin->assignRole(User::SUPERADMIN);

    expect(Gate::forUser($superadmin)->allows('anything'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create())->allows('anything'))->toBeFalse();
});
