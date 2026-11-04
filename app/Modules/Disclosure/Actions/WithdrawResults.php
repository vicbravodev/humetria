<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Disclosure\Models\ResultPublication;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RH takes the results of an evaluation off the page of the people (criterio C-54). The publication stays, marked as withdrawn
 * and by whom (num. 10.4); publishing again makes a new one.
 */
final readonly class WithdrawResults
{
    public function __construct(private RecordAudit $audit) {}

    /**
     * @throws ModelNotFoundException when the evaluation has no active publication in this company.
     */
    public function handle(string $campaignId, int $userId): ResultPublication
    {
        return DB::transaction(function () use ($campaignId, $userId): ResultPublication {
            $publication = ResultPublication::query()->where('campaign_id', $campaignId)->whereNull('withdrawn_at')->lockForUpdate()->firstOrFail();
            $publication->update(['withdrawn_at' => now(), 'withdrawn_by' => $userId]);

            $record = ['campaign_id' => $publication->campaign_id, 'work_center_id' => $publication->work_center_id];
            Log::info('disclosure.results_withdrawn', $record);
            $this->audit->handle('disclosure.results_withdrawn', $publication, $record);

            return $publication;
        });
    }
}
