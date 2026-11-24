<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Models\UsageCounter;
use Illuminate\Support\Facades\Log;

/** Gives back, for every company, what a bulk send held on the mail and WhatsApp meters and nobody used (people without an address or a phone, a message that never left). */
final readonly class ReleaseBulkHolds
{
    private const array METERS = [UsageMeter::EMAIL_CAMPAIGN, UsageMeter::WHATSAPP];

    /** @return int how many holds it released. */
    public function handle(): int
    {
        $held = UsageCounter::query()->whereIn('meter', self::METERS)->where('reserved', '>', 0);

        $released = (int) (clone $held)->sum('reserved');
        $held->update(['reserved' => 0]);

        Log::info('usage.holds_released', ['released' => $released]);

        return $released;
    }
}
