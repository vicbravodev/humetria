<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Data\WorkCenterData;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;

final class UpdateWorkCenter
{
    public function handle(WorkCenter $workCenter, WorkCenterData $data): WorkCenter
    {
        $workCenter->update($data->attributes());

        // sort() also renumbers the keys, so the list stays a list once updated_at is taken out.
        $changed = array_diff(array_keys($workCenter->getChanges()), ['updated_at']);
        sort($changed);

        if ($changed !== []) {
            Log::info('work_center.updated', ['work_center_id' => $workCenter->id, 'changed' => $changed]);
        }

        return $workCenter;
    }
}
