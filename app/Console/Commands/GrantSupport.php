<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SetSupportRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Gives the platform's support role to a user who already has an account: it sees the console and works as an owner,
 * and changes nothing about a company. Like the superadmin's role, it can only be given from here.
 */
#[Signature('humetria:grant-support {email : Correo de la persona, que ya debe tener cuenta}')]
#[Description('Da el rol de soporte de la plataforma a un usuario existente')]
final class GrantSupport extends Command
{
    public function handle(SetSupportRole $setSupportRole): int
    {
        $email = Str::lower(trim($this->argument('email')));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No hay ningún usuario con el correo {$email}.");

            return self::FAILURE;
        }

        if ($user->isSuperadmin()) {
            $this->error("{$email} es superadmin: no se le da el rol de soporte.");

            return self::FAILURE;
        }

        if (! $setSupportRole->grant($user)) {
            $this->info("{$email} ya es soporte.");

            return self::SUCCESS;
        }

        $this->info("{$email} ahora es soporte.");

        return self::SUCCESS;
    }
}
