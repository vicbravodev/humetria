<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Tenancy\Contracts\CompanyLimits;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('humetria:reconcile-limits')]
#[Description('Recount the people and work centers of every company with a plan and fix the counters that drifted')]
final class ReconcileLimits extends Command
{
    public function handle(CompanyLimits $limits): int
    {
        $this->info('Contadores corregidos: '.count($limits->reconcile()));

        return self::SUCCESS;
    }
}
