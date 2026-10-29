<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Data\WorkCenterData;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;

final readonly class CreateWorkCenter
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function handle(WorkCenterData $data): WorkCenter
    {
        $workCenter = WorkCenter::query()->create([
            'tenant_id' => $this->currentTenant->id(),
            ...$data->attributes(),
        ]);

        Log::info('work_center.created', ['work_center_id' => $workCenter->id]);

        return $workCenter;
    }
}
