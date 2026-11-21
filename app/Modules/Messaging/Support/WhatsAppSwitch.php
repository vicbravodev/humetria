<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Models\Pause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Whether WhatsApp may send at all: the function is on, the operator has not paused it, and no automatic pause is open.
 */
final class WhatsAppSwitch
{
    public function available(): bool
    {
        return config()->boolean('whatsapp.enabled')
            && ! config()->boolean('whatsapp.paused')
            && ! Pause::query()->whereNull('ended_at')->exists();
    }

    /** Opens a pause (the unique index allows one) and says so as an error: only an operator closes it. */
    public function pause(string $reason): void
    {
        $opened = DB::table('messaging_pauses')->insertOrIgnore(['id' => (string) Str::uuid(), 'reason' => $reason, 'started_at' => now()]);

        if ($opened > 0) {
            Log::error('whatsapp.paused_automatically', ['reason' => $reason]);
        }
    }
}
