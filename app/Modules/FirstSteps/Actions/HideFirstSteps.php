<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Actions;

use App\Modules\FirstSteps\Models\FirstStepsState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Hides the card of the guided tour for the owner; they can bring it back from their menu.
 */
final readonly class HideFirstSteps
{
    public function handle(string $tenantId, int $userId): void
    {
        $changed = DB::transaction(function () use ($tenantId, $userId): bool {
            $state = FirstStepsState::query()->firstOrCreate(['tenant_id' => $tenantId, 'user_id' => $userId]);
            if ($state->hidden_at !== null) {
                return false;
            }

            $state->hidden_at = now();

            return $state->save();
        });

        if ($changed) {
            Log::info('first_steps.hidden', ['tenant_id' => $tenantId, 'user_id' => $userId]);
        }
    }
}
