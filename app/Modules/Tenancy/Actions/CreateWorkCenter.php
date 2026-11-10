<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Data\WorkCenterData;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class CreateWorkCenter
{
    public function __construct(private CurrentTenant $currentTenant, private CompanyLimits $limits) {}

    /**
     * @throws ValidationException when the plan has no work center left.
     */
    public function handle(WorkCenterData $data): WorkCenter
    {
        // The place is taken in the same transaction as the center: two requests for the last one cannot both pass.
        $workCenter = DB::transaction(function () use ($data): WorkCenter {
            $this->limits->reserve(CompanyLimits::CENTERS, 1, 'centers');

            return WorkCenter::query()->create([
                'tenant_id' => $this->currentTenant->id(),
                ...$data->attributes(),
            ]);
        });

        Log::info('work_center.created', ['work_center_id' => $workCenter->id]);

        return $workCenter;
    }
}
