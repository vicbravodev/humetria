<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Actions\RecordAudit;
use App\Modules\Telework\Models\TeleworkProfile;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * A work center declares whether someone works away from it more than 40 % of their time (LFT 330-A), so whether NOM-037
 * applies there. Declaring again replaces the declaration, with the new date and author.
 */
final readonly class DeclareApplicability
{
    public function __construct(private CurrentTenant $tenant, private RecordAudit $audit) {}

    public function handle(string $workCenterId, bool $hasTeleworkers, int $userId): TeleworkProfile
    {
        return DB::transaction(function () use ($workCenterId, $hasTeleworkers, $userId): TeleworkProfile {
            $profile = TeleworkProfile::query()->updateOrCreate(['work_center_id' => $workCenterId], [
                'tenant_id' => $this->tenant->id(),
                'has_teleworkers' => $hasTeleworkers,
                'declared_by' => $userId,
                'declared_on' => CarbonImmutable::now(config()->string('app.display_timezone'))->toDateString(),
            ]);

            $record = ['work_center_id' => $workCenterId, 'has_teleworkers' => $hasTeleworkers];
            $this->audit->handle('telework.applicability_declared', $profile, $record);
            Log::info('telework.applicability_declared', $record);

            return $profile;
        });
    }
}
