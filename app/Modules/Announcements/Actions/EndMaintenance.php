<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Actions;

use App\Actions\RecordAudit;
use App\Modules\Announcements\Models\MaintenancePeriod;
use App\Modules\Announcements\Support\LiveNotices;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class EndMaintenance
{
    public function __construct(private RecordAudit $recordAudit, private LiveNotices $live) {}

    /** Gives every company its panel back. Without a maintenance running, nothing changes. */
    public function handle(): void
    {
        DB::transaction(function (): void {
            $period = MaintenancePeriod::query()->whereNull('ended_at')->lockForUpdate()->first();

            if ($period === null) {
                return;
            }

            $period->update(['ended_at' => now()]);

            Log::info('announcement.maintenance_ended', ['maintenance_id' => $period->id]);
            $this->recordAudit->handle('announcement.maintenance_ended', $period);
        });

        $this->live->refresh();
    }
}
