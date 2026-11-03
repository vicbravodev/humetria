<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Models\PauseAgreement;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\EvidenceStore;
use App\Modules\Seating\Support\SeatingFiles;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records what was agreed with the people of a position about their pauses and how it was reached (criterio C-14). The law
 * fixes no number of pauses nor a minimum of minutes, so none is imposed. A new agreement is a new row: the earlier ones stay.
 */
final readonly class SavePauseAgreement
{
    public function __construct(private CurrentTenant $tenant, private EvidenceStore $evidence, private SeatingFiles $files) {}

    public function handle(StandingPosition $position, int $pausesPerShift, int $minutesEach, string $agreedOn, string $howAgreed, ?string $evidenceFile, int $userId): PauseAgreement
    {
        $tenantId = $this->tenant->id();
        $stored = $evidenceFile === null ? null : $this->evidence->store($evidenceFile, "agreements/{$tenantId}/{$position->work_center_id}");

        try {
            return DB::transaction(function () use ($position, $pausesPerShift, $minutesEach, $agreedOn, $howAgreed, $stored, $tenantId, $userId): PauseAgreement {
                $agreement = PauseAgreement::query()->create([
                    'tenant_id' => $tenantId,
                    'work_center_id' => $position->work_center_id,
                    'position_id' => $position->id,
                    'pauses_per_shift' => $pausesPerShift,
                    'minutes_each' => $minutesEach,
                    'agreed_on' => $agreedOn,
                    'how_agreed' => $howAgreed,
                    'evidence_path' => $stored,
                    'created_by' => $userId,
                ]);

                Log::info('seating.pause_agreement_saved', ['agreement_id' => $agreement->id, 'position_id' => $position->id, 'work_center_id' => $position->work_center_id]);

                return $agreement;
            });
        } catch (Throwable $failure) {
            if ($stored !== null) {
                $this->files->delete($stored);
            }

            throw $failure;
        }
    }
}
