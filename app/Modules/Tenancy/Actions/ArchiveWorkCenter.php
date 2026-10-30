<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;

final class ArchiveWorkCenter
{
    /**
     * Takes the work center out of the list. Nothing is deleted: its evaluations stay as evidence (num. 10.4).
     */
    public function handle(WorkCenter $workCenter): WorkCenter
    {
        $workCenter->update(['archived_at' => now()]);

        Log::info('work_center.archived', ['work_center_id' => $workCenter->id]);

        return $workCenter;
    }
}
