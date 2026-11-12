<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Actions;

use App\Modules\FirstSteps\Models\FirstStepsState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Records that the owner saw the welcome of the guided tour, once: the first time stays.
 */
final readonly class MarkWelcomed
{
    public function handle(string $tenantId, int $userId): void
    {
        $changed = DB::transaction(function () use ($tenantId, $userId): bool {
            $state = FirstStepsState::query()->firstOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId]);
            if ($state->welcomed_at !== null) {
                return false;
            }

            $state->welcomed_at = now();

            return $state->save();
        });

        if ($changed) {
            Log::info('first_steps.welcomed', ['tenant_id' => $tenantId, 'user_id' => $userId]);
        }
    }
}
