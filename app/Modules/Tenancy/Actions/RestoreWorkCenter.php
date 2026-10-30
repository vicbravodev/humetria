<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;

final class RestoreWorkCenter
{
    /**
     * Brings an archived work center back to the list, with its history.
     */
    public function handle(WorkCenter $workCenter): WorkCenter
    {
        $workCenter->update(['archived_at' => null]);

        Log::info('work_center.restored', ['work_center_id' => $workCenter->id]);

        return $workCenter;
    }
}
