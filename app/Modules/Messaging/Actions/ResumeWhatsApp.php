<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Actions;

use App\Modules\Messaging\Models\Pause;
use Illuminate\Support\Facades\Log;

/**
 * An operator closes the open pause (the automatic one or the platform cap). Nothing that was left unsent is sent by itself: the
 * messages stay as they ended (rule 9).
 */
final class ResumeWhatsApp
{
    /** @return int how many pauses it closed */
    public function handle(): int
    {
        $closed = Pause::query()->whereNull('ended_at')->update(['ended_at' => now()]);

        if ($closed > 0) {
            Log::info('whatsapp.resumed');
        }

        return $closed;
    }
}
