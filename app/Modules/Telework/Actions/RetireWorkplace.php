<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\Workplace;
use Illuminate\Support\Facades\Log;

/**
 * Marks a workplace as no longer in use (the person moved, or the Comisión judged it not apt). It is kept as evidence:
 * only `retired_at` is set, and asking again keeps the first date.
 */
final readonly class RetireWorkplace
{
    public function handle(Workplace $workplace): void
    {
        if ($workplace->retired_at !== null) {
            return;
        }

        $workplace->update(['retired_at' => now()]);
        Log::info('telework.workplace_retired', ['teleworker_id' => $workplace->teleworker_id, 'workplace_id' => $workplace->id]);
    }
}
