<?php

declare(strict_types=1);

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveSetting;
use Illuminate\Support\Facades\Log;

/**
 * The working week the «Ley» policy counts days by (criterio C-04). Requests already made keep their working days; the
 * day the company started tracking is never changed from here.
 */
final readonly class SaveWorkWeek
{
    public function handle(WorkWeek $week): void
    {
        LeaveSetting::query()->sole()->update(['statutory_work_week' => $week]);
        Log::info('leave.work_week_saved', ['work_week' => $week->value]);
    }
}
