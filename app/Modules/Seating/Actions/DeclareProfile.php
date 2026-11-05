<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Actions\RecordAudit;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use App\Modules\Seating\Support\Today;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A work center declares its sector (criterio C-03) and whether it has people working standing; where nobody does, the
 * sector may be left undeclared. Declaring again replaces the declaration, with the new date and author.
 */
final readonly class DeclareProfile
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(string $workCenterId, ?Sector $sector, bool $noStandingPositions, ?string $note, int $userId): SeatingProfile
    {
        return DB::transaction(function () use ($workCenterId, $sector, $noStandingPositions, $note, $userId): SeatingProfile {
            $profile = SeatingProfile::query()->updateOrCreate(['work_center_id' => $workCenterId], [
                'tenant_id' => $this->tenant->id(),
                'sector' => $sector,
                'no_standing_positions' => $noStandingPositions,
                'declared_by' => $userId,
                'declared_on' => Today::date(),
                'note' => $note,
            ]);

            $record = ['work_center_id' => $workCenterId, 'sector' => $sector?->value, 'no_standing_positions' => $noStandingPositions];
            $this->audit->handle('seating.profile_declared', $profile, $record);
            Log::info('seating.profile_declared', $record);

            return $profile;
        });
    }
}
