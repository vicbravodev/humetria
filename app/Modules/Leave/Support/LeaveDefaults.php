<?php

declare(strict_types=1);

namespace App\Modules\Leave\Support;

use App\Modules\Leave\Models\LeaveSetting;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * What a company needs to use Vacaciones, created the first time its panel is opened: the row that says since which day
 * its periods are granted (criterio C-21) and the leave types it starts with (criterio C-19). Asking again changes nothing.
 */
final readonly class LeaveDefaults
{
    public function __construct(private CurrentTenant $tenant) {}

    public function ensure(): LeaveSetting
    {
        $today = PeriodWindow::today()->toDateString();
        $setting = LeaveSetting::query()->firstOrCreate(['tenant_id' => $this->tenant->id()], ['tracking_since' => $today]);

        $now = now();
        // The unique key of a company's seeded types settles a repeat: only the missing ones go in.
        LeaveType::query()->insertOrIgnore(array_map(fn (array $type): array => [
            'id' => (string) Str::uuid(), 'tenant_id' => $setting->tenant_id, 'key' => $type[0], 'name' => $type[1], 'paid' => $type[2], 'created_at' => $now, 'updated_at' => $now,
        ], LeaveType::SEEDED));

        if ($setting->wasRecentlyCreated) {
            Log::info('leave.tracking_started', ['tracking_since' => $today]);
        }

        // A row just created holds only what was given: the working week comes from the column's default.
        return $setting->wasRecentlyCreated ? $setting->refresh() : $setting;
    }
}
