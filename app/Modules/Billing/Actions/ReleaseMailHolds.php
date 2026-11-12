<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Listeners\CountSentMail;
use App\Modules\Billing\Models\UsageCounter;
use Illuminate\Support\Facades\Log;

/** Gives back, for every company, the campaign mail a bulk send held and nobody used (people without an address, a mail that never left). */
final readonly class ReleaseMailHolds
{
    /** @return int how many holds it released. */
    public function handle(): int
    {
        $released = (int) UsageCounter::query()->where('meter', CountSentMail::METER)->where('reserved', '>', 0)->sum('reserved');
        UsageCounter::query()->where('meter', CountSentMail::METER)->where('reserved', '>', 0)->update(['reserved' => 0]);

        Log::info('usage.mail_holds_released', ['released' => $released]);

        return $released;
    }
}
