<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Actions;

use App\Modules\FirstSteps\Models\FirstStepsState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Brings back the card of the guided tour the owner had hidden.
 */
final readonly class ShowFirstSteps
{
    public function handle(string $tenantId, int $userId): void
    {
        $changed = DB::transaction(function () use ($tenantId, $userId): bool {
            $state = FirstStepsState::query()->firstOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId]);
            if ($state->hidden_at === null) {
                return false;
            }

            $state->hidden_at = null;

            return $state->save();
        });

        if ($changed) {
            Log::info('first_steps.shown', ['tenant_id' => $tenantId, 'user_id' => $userId]);
        }
    }
}
