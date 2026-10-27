<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\RecordAudit;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

#[Signature('humetria:grant-superadmin {email : Correo de un usuario existente}')]
#[Description('Da el rol de superadmin a un usuario existente')]
final class GrantSuperadmin extends Command
{
    public function handle(RecordAudit $recordAudit): int
    {
        $email = $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No existe un usuario con el correo {$email}.");

            return self::FAILURE;
        }

        $user->assignRole(User::SUPERADMIN);
        Log::info('user.superadmin_granted', ['user_id' => $user->id]);
        $recordAudit->handle('user.superadmin_granted', $user);

        $this->info("{$email} ahora es superadmin.");

        return self::SUCCESS;
    }
}
