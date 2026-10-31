<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\OpenedPortal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The person authorizes the visit from their portal: the time and the address they confirmed from are kept as evidence
 * (like an acknowledgement, designer decision 6). The address is never logged.
 */
final readonly class ConfirmVisitAuthorization
{
    /**
     * @throws ModelNotFoundException when the authorization is not for one of this person's workplaces.
     * @throws ValidationException when it was already given.
     */
    public function handle(OpenedPortal $portal, string $authorizationId, string $ip): void
    {
        $teleworkerId = $portal->teleworkerId ?? throw new ModelNotFoundException;

        DB::transaction(function () use ($teleworkerId, $authorizationId, $ip): void {
            $authorization = VisitAuthorization::query()
                ->whereIn('workplace_id', Workplace::query()->where('teleworker_id', $teleworkerId)->whereNull('retired_at')->select('id'))
                ->lockForUpdate()
                ->findOrFail($authorizationId);

            if ($authorization->authorized()) {
                throw ValidationException::withMessages(['visit' => 'Esta visita ya está autorizada.']);
            }

            $authorization->update(['confirmed_at' => now(), 'confirmed_ip' => $ip]);

            Log::info('telework.visit_authorization_confirmed', ['visit_authorization_id' => $authorization->id, 'workplace_id' => $authorization->workplace_id]);
        });
    }
}
