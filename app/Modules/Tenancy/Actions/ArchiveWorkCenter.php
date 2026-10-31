<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Campaigns\Contracts\OpenCampaigns;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final readonly class ArchiveWorkCenter
{
    public function __construct(private OpenCampaigns $openCampaigns) {}

    /**
     * Takes the work center out of the list. Nothing is deleted: its evaluations stay as evidence (num. 10.4). One with
     * an evaluation in progress stays until it is closed: its people are still answering.
     *
     * @throws ValidationException
     */
    public function handle(WorkCenter $workCenter): WorkCenter
    {
        DB::transaction(function () use ($workCenter): void {
            // Under lock: a draft of it being created at the same time waits here, or this finds it.
            WorkCenter::query()->lockForUpdate()->findOrFail($workCenter->id);

            if ($this->openCampaigns->existFor($workCenter->id)) {
                throw ValidationException::withMessages(['work_center' => 'Este centro tiene una evaluación NOM-035 abierta. Ciérrala o elimina su borrador antes de archivarlo.']);
            }

            $workCenter->update(['archived_at' => now()]);
        });

        Log::info('work_center.archived', ['work_center_id' => $workCenter->id]);

        return $workCenter;
    }
}
