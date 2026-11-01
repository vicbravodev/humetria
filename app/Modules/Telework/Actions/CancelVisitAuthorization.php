<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\WorkplaceReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * RH or the Comisión cancels a visit request that was never used (the person did not answer, the visit is off). It is
 * marked, never deleted: evidence stays. A request that already backs a recorded visit cannot be cancelled.
 */
final readonly class CancelVisitAuthorization
{
    /**
     * @throws ValidationException when it was already used or cancelled.
     */
    public function handle(VisitAuthorization $authorization, int $userId): void
    {
        DB::transaction(function () use ($authorization, $userId): void {
            // Locked: a visit being recorded and a cancellation cannot both win.
            $fresh = VisitAuthorization::query()->lockForUpdate()->findOrFail($authorization->id);

            if ($fresh->cancelled_at !== null || WorkplaceReview::query()->where('visit_authorization_id', $fresh->id)->exists()) {
                throw ValidationException::withMessages(['visit' => 'Esta solicitud ya se usó o ya fue cancelada.']);
            }

            $fresh->update(['cancelled_at' => now(), 'cancelled_by' => $userId]);

            Log::info('telework.visit_authorization_cancelled', ['visit_authorization_id' => $fresh->id, 'workplace_id' => $fresh->workplace_id, 'cancelled_by' => $userId]);
        });
    }
}
