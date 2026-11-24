<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\VAPID;

/** Makes a pair of VAPID keys for the environment. It only prints them: it never writes the `.env`, and the log never has them. */
final class GenerateVapidKeys extends Command
{
    protected $signature = 'notifications:vapid-keys';

    protected $description = 'Genera las llaves VAPID de los avisos push (se imprimen; no se escribe el .env)';

    public function handle(): int
    {
        $keys = VAPID::createVapidKeys();

        $this->line('VAPID_PUBLIC_KEY='.Arr::string($keys, 'publicKey'));
        $this->line('VAPID_PRIVATE_KEY='.Arr::string($keys, 'privateKey'));
        $this->newLine();
        $this->components->warn('Cambiar las llaves de un ambiente que ya las usa invalida toda suscripción: la app de cada persona se vuelve a suscribir al abrirla.');

        Log::info('push.vapid_keys_generated');

        return self::SUCCESS;
    }
}
