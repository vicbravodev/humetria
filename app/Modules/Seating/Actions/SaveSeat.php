<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Seating\Data\SeatData;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\SeatingFiles;
use App\Modules\Seating\Support\SeatPhotoStore;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Registers or edits a seat. An area designated for the rests needs its location (criterio C-01); the position, if any, must
 * be of the same center. A new photo replaces the old one, which is deleted once the change is committed.
 */
final readonly class SaveSeat
{
    public function __construct(private CurrentTenant $tenant, private SeatPhotoStore $photos, private SeatingFiles $files, private RecordAudit $audit) {}

    /**
     * @throws ValidationException
     */
    public function handle(SeatData $data, ?string $photoFile): Seat
    {
        if ($data->use === SeatUse::RestArea && $data->location === null) {
            throw ValidationException::withMessages(['location' => 'Indica dónde está el área designada para los descansos.']);
        }

        if ($data->positionId !== null && ! StandingPosition::query()->where('id', $data->positionId)->where('work_center_id', $data->workCenterId)->exists()) {
            throw ValidationException::withMessages(['position_id' => 'Ese puesto no es de este centro de trabajo.']);
        }

        $tenantId = $this->tenant->id();
        $stored = $photoFile === null ? null : $this->photos->store($photoFile, "seats/{$tenantId}/{$data->workCenterId}");

        try {
            [$seat, $replaced] = DB::transaction(function () use ($data, $tenantId, $stored): array {
                $seat = $data->id === null ? new Seat : Seat::query()->findOrFail($data->id);
                $previous = $seat->photo_path;
                $seat->fill([
                    'tenant_id' => $tenantId,
                    'work_center_id' => $data->workCenterId,
                    'position_id' => $data->positionId,
                    'location' => $data->location,
                    'kind' => $data->kind,
                    'use' => $data->use,
                    'quantity' => $data->quantity,
                    'condition' => $data->condition,
                    'signposted' => $data->signposted,
                    'photo_path' => $stored ?? $previous,
                ]);

                foreach ($data->characteristics as $feature => $has) {
                    $seat->setAttribute($feature, $has);
                }

                $seat->save();

                $record = ['work_center_id' => $data->workCenterId, 'seat_id' => $seat->id, 'use' => $data->use->value];
                $this->audit->handle($data->id === null ? 'seating.seat_registered' : 'seating.seat_updated', $seat, $record);
                Log::info($data->id === null ? 'seating.seat_registered' : 'seating.seat_updated', $record);

                return [$seat, $stored !== null ? $previous : null];
            });
        } catch (Throwable $failure) {
            // The photo went in before the commit: a failed save must not leave it behind.
            if ($stored !== null) {
                $this->files->delete($stored);
            }

            throw $failure;
        }

        if ($replaced !== null) {
            $this->files->delete($replaced);
        }

        return $seat;
    }
}
