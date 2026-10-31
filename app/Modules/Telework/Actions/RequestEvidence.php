<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The Comisión asks for more evidence about a workplace: photos from the person (they appear in their portal, and
 * nowhere else: designer decision 15) or a remote check the Comisión does itself. Who may ask is the controller's business.
 */
final readonly class RequestEvidence
{
    /**
     * @throws ValidationException
     */
    public function handle(Workplace $workplace, EvidenceKind $kind, string $note, int $userId): EvidenceRequest
    {
        $note = trim($note);

        if ($workplace->retired_at !== null) {
            throw ValidationException::withMessages(['workplace' => 'Este lugar ya está retirado.']);
        }

        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'Escribe qué necesitas.']);
        }

        $request = EvidenceRequest::query()->create([
            'tenant_id' => $workplace->tenant_id,
            'workplace_id' => $workplace->id,
            'kind' => $kind,
            'note' => $note,
            'requested_by' => $userId,
            'requested_at' => now(),
        ]);

        // Ids and the kind only: the note says what is wrong with somebody's home.
        Log::info('telework.evidence_requested', ['evidence_request_id' => $request->id, 'workplace_id' => $workplace->id, 'kind' => $kind->value]);

        return $request;
    }
}
