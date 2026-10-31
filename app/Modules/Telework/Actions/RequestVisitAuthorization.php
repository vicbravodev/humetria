<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The Comisión asks the person to authorize, in writing, an in-person visit to their workplace. It shows in their portal
 * until they confirm (or RH attaches the signed paper). One request waits at a time per workplace.
 */
final readonly class RequestVisitAuthorization
{
    /**
     * @throws ValidationException
     */
    public function handle(Workplace $workplace, int $userId): VisitAuthorization
    {
        if ($workplace->retired_at !== null) {
            throw ValidationException::withMessages(['workplace' => 'Este lugar ya está retirado.']);
        }

        $unused = VisitAuthorization::query()
            ->where('workplace_id', $workplace->id)
            ->whereNotIn('id', WorkplaceReview::query()->whereNotNull('visit_authorization_id')->select('visit_authorization_id'))
            ->exists();

        if ($unused) {
            throw ValidationException::withMessages(['visit' => 'Ya hay una autorización de visita pendiente para este lugar.']);
        }

        $authorization = VisitAuthorization::query()->create([
            'tenant_id' => $workplace->tenant_id,
            'workplace_id' => $workplace->id,
            'requested_by' => $userId,
            'requested_at' => now(),
        ]);

        Log::info('telework.visit_authorization_requested', ['visit_authorization_id' => $authorization->id, 'workplace_id' => $workplace->id]);

        return $authorization;
    }
}
