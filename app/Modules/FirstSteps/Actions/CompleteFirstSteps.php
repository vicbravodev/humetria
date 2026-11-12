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
        FirstStepsState::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'user_id' => $userId],
            ['completed_at' => now()],
        );

        Log::info('first_steps.completed', ['tenant_id' => $tenantId, 'user_id' => $userId]);
    }
}
