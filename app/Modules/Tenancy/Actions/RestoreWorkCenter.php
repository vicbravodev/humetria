<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class RestoreWorkCenter
{
    public function __construct(private CompanyLimits $limits) {}

    /**
     * Brings an archived work center back to the list, with its history. It takes a place of the plan again: with none left it stays archived.
     *
     * @throws ValidationException
     */
    public function handle(WorkCenter $workCenter): WorkCenter
    {
        DB::transaction(function () use ($workCenter): void {
            $this->limits->reserve(CompanyLimits::CENTERS, 1, 'centers');
            $workCenter->update(['archived_at' => null]);
        });

        Log::info('work_center.restored', ['work_center_id' => $workCenter->id]);

        return $workCenter;
    }
}
