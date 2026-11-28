<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Actions;

use App\Actions\RecordAudit;
use App\Modules\Announcements\Models\MaintenancePeriod;
use App\Modules\Announcements\Support\LiveNotices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class StartMaintenance
{
    public function __construct(private RecordAudit $recordAudit, private LiveNotices $live) {}

    /** Puts every company's panel in read only until a person ends it. Its portals, devices and surveys go on. */
    public function handle(string $reason): MaintenancePeriod
    {
        $period = DB::transaction(function () use ($reason): MaintenancePeriod {
            $period = MaintenancePeriod::query()->create(['reason' => $reason, 'started_at' => now()]);

            // A warning: every company stops saving until it ends.
            Log::warning('announcement.maintenance_started', ['maintenance_id' => $period->id]);
            $this->recordAudit->handle('announcement.maintenance_started', $period, ['reason' => $reason]);

            return $period;
        });

        $this->live->refresh();

        return $period;
    }
}
