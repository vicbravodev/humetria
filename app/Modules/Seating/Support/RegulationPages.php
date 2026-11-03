<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\RegulationStatus;
use App\Modules\Seating\Models\InternalRegulation;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Facades\Gate;

/**
 * What the regulation page sends to the browser: the parts, where it stands, the next step and the notice that it is not
 * legal advice.
 */
final readonly class RegulationPages
{
    /**
     * @return array<string, mixed>
     */
    public function show(mixed $tenant, WorkCenterSummary $center): array
    {
        $regulation = InternalRegulation::query()->where('work_center_id', $center->id)->first();

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'regulation' => $regulation === null ? null : [
                'status' => $regulation->status->value,
                'statusLabel' => $regulation->status->label(),
                'parts' => $regulation->parts,
                'signedOn' => $regulation->signed_on?->toDateString(),
                'depositedOn' => $regulation->deposited_on?->toDateString(),
                'depositFolio' => $regulation->deposit_folio,
                'distributedOn' => $regulation->distributed_on?->toDateString(),
                'fileHref' => $regulation->file_path === null ? null : route('tenant.seating.regulation.file', [$tenant, $center->id]),
                'nextStep' => match ($regulation->status) {
                    RegulationStatus::Draft => 'signed',
                    RegulationStatus::Signed => 'deposited',
                    RegulationStatus::Deposited => 'distributed',
                    RegulationStatus::Distributed => null,
                },
            ],
            'notice' => RegulationNotice::TEXT,
            'canManage' => Gate::allows('manage-seating'),
        ];
    }
}
