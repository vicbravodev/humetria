<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Data\RegulationParts;
use App\Modules\Seating\Enums\RegulationStatus;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Seating\Support\RegulationDraft;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Drafts the internal regulation of a center from its data, or saves what RH edited in the draft (criterio C-13). There is
 * one per center: drafting again leaves it as it is, and a regulation already signed is not edited.
 */
final readonly class DraftRegulation
{
    public function __construct(private CurrentTenant $tenant, private RegulationDraft $draft) {}

    /**
     * @param  RegulationParts|null  $edited  The parts RH wrote; null to draft from the data of the center.
     *
     * @throws ValidationException when the regulation is already signed.
     */
    public function handle(string $workCenterId, ?RegulationParts $edited, int $userId): InternalRegulation
    {
        return DB::transaction(function () use ($workCenterId, $edited, $userId): InternalRegulation {
            $existing = InternalRegulation::query()->where('work_center_id', $workCenterId)->lockForUpdate()->first();

            if ($existing === null) {
                $regulation = InternalRegulation::query()->create([
                    'tenant_id' => $this->tenant->id(),
                    'work_center_id' => $workCenterId,
                    'parts' => ($edited ?? $this->draft->for($workCenterId))->toArray(),
                    'status' => RegulationStatus::Draft,
                    'created_by' => $userId,
                ]);
                Log::info('seating.regulation_drafted', ['regulation_id' => $regulation->id, 'work_center_id' => $workCenterId]);

                return $regulation;
            }

            if ($edited === null) {
                return $existing;
            }

            if ($existing->status !== RegulationStatus::Draft) {
                throw ValidationException::withMessages(['status' => 'El reglamento ya está firmado y no se edita.']);
            }

            $existing->update(['parts' => $edited->toArray()]);
            Log::info('seating.regulation_saved', ['regulation_id' => $existing->id, 'work_center_id' => $workCenterId]);

            return $existing;
        });
    }
}
