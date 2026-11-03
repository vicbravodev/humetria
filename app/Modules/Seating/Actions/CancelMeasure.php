<?php

declare(strict_types=1);

namespace App\Modules\Seating\Actions;

use App\Modules\Seating\Enums\MeasureStatus;
use App\Modules\Seating\Models\PreventiveMeasure;
use Illuminate\Support\Facades\Log;

/**
 * Cancels a measure: it stays in the plan as «cancelada» (criterio C-17). Cancelling it again changes nothing.
 */
final readonly class CancelMeasure
{
    public function handle(PreventiveMeasure $measure): PreventiveMeasure
    {
        if ($measure->status === MeasureStatus::Cancelled) {
            return $measure;
        }

        $measure->update(['status' => MeasureStatus::Cancelled]);
        Log::info('seating.measure_saved', ['measure_id' => $measure->id, 'work_center_id' => $measure->work_center_id, 'kind' => $measure->kind->value, 'status' => 'cancelled']);

        return $measure;
    }
}
