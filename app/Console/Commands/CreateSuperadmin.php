<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RecordAudit;
use App\Concerns\PasswordValidationRules;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Bootstraps the platform: registration is closed, so the first superadmin can only come from here.
 * Once one exists, the next ones are created from the application.
 */
#[Signature('humetria:create-superadmin')]
#[Description('Crea el primer superadmin de la plataforma')]
final class CreateSuperadmin extends Command
{
    use PasswordValidationRules;

    public function handle(RecordAudit $recordAudit): int
    {
        if (User::role(User::SUPERADMIN)->exists()) {
            $this->error('Ya existe un superadmin. Los siguientes se crean desde la aplicación.');

            return self::FAILURE;
        }

        $input = [
            'name' => trim(text('Nombre')),
            'email' => Str::lower(trim(text('Correo'))),
            'password' => password('Contraseña'),
            'password_confirmation' => password('Confirma la contraseña'),
        ];

        $validator = Validator::make($input, [
            'name' => ['required', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => $this->passwordRules(),
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($input, $recordAudit): void {
            $user = new User;
            $user->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'email_verified_at' => now(),
            ])->save();
            $user->assignRole(User::SUPERADMIN);

            Log::info('user.superadmin_created', ['user_id' => $user->id]);
            $recordAudit->handle('user.superadmin_created', $user);
        });

        $this->info("{$input['email']} ahora es superadmin.");

        return self::SUCCESS;
    }
}
