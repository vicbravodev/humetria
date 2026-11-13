<?php

declare(strict_types=1);

namespace App\Modules\FirstSteps\Actions;

use App\Modules\FirstSteps\Models\FirstStepsState;
use Illuminate\Support\Facades\Log;

/**
 * Records that a person finished the guided tour of a company: it never comes back.
 */
final readonly class CompleteFirstSteps
{
    public function handle(string $tenantId, int $userId): void
    {
        // createOrFirst: two first writes at once must not trip the unique index.
        $state = FirstStepsState::query()->createOrFirst(['tenant_id' => $tenantId, 'user_id' => $userId]);
        $state->completed_at = now();
        $state->save();

        Log::info('first_steps.completed', ['tenant_id' => $tenantId, 'user_id' => $userId]);
    }
}
