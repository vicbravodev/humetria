<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;

/**
 * What a company needs to use Vacaciones, created the first time its panel is opened: the row that says since which day
 * its periods are granted (criterio C-21). Asking again changes nothing.
 */
final readonly class LeaveDefaults
{
    public function __construct(private CurrentTenant $tenant) {}

    public function ensure(): LeaveSetting
    {
        $today = PeriodWindow::today()->toDateString();
        $setting = LeaveSetting::query()->firstOrCreate(['tenant_id' => $this->tenant->id()], ['tracking_since' => $today]);

        if ($setting->wasRecentlyCreated) {
            Log::info('leave.tracking_started', ['tracking_since' => $today]);
        }

        return $setting;
    }
}
