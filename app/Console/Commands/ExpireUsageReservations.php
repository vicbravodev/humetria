<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Billing\Actions\ExpireUsageReservations as ExpireReservations;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('billing:expire-usage-reservations')]
#[Description('Give back the holds of calls nobody settled or released in time, without counting them as used')]
final class ExpireUsageReservations extends Command
{
    public function handle(ExpireReservations $expire): int
    {
        $this->info('Reservas vencidas: '.$expire->handle());

        return self::SUCCESS;
    }
}
