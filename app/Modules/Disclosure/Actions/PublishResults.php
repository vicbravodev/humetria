<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Actions\RecordAudit;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Disclosure\Models\ResultPublication;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * RH publishes the results of a closed evaluation to the people of its center (num. 7.8; criterio C-54). Only an evaluation that
 * is closed (C-27) and that is scored (not the Guía I alone) can be published; whether figures are shown is Results' to decide,
 * with the minimum applied (C-23), and below it the page says so without a count. Publishing what is already published changes
 * nothing (the choice to show the measures of the Programa is the one it was made with). The audit trail and the log keep the evaluation and the center, never a figure.
 */
final readonly class PublishResults
{
    public function __construct(private CurrentTenant $tenant, private CampaignRecords $campaigns, private RiskResults $results, private RecordAudit $audit) {}

    /**
     * @throws ValidationException when the evaluation still runs or has no scored guide.
     * @throws ModelNotFoundException when it does not exist or is another company's.
     */
    public function handle(string $campaignId, int $userId, bool $showMeasures = false): ResultPublication
    {
        $record = Str::isUuid($campaignId) ? $this->campaigns->find($campaignId) : null;

        if ($record === null) {
            throw new ModelNotFoundException;
        }

        if (in_array($this->results->forCampaign($record)->state, [RiskBreakdown::OPEN, RiskBreakdown::NOT_SCORED], true)) {
            throw ValidationException::withMessages(['campaign' => 'Esta evaluación no tiene resultados que las personas puedan consultar: sigue abierta o solo aplicó la Guía I.']);
        }

        return $this->active($record->id) ?? DB::transaction(function () use ($record, $userId, $showMeasures): ResultPublication {
            try {
                $publication = ResultPublication::query()->create(['tenant_id' => $this->tenant->id(), 'campaign_id' => $record->id, 'work_center_id' => $record->workCenterId, 'show_measures' => $showMeasures, 'published_at' => now(), 'published_by' => $userId]);
            } catch (UniqueConstraintViolationException) {
                // Two requests raced: the one that lost reads the one that won.
                return $this->active($record->id) ?? throw new ModelNotFoundException;
            }

            $facts = ['campaign_id' => $record->id, 'work_center_id' => $record->workCenterId, 'show_measures' => $showMeasures];
            Log::info('disclosure.results_published', $facts);
            $this->audit->handle('disclosure.results_published', $publication, $facts);

            return $publication;
        });
    }

    private function active(string $campaignId): ?ResultPublication
    {
        return ResultPublication::query()->where('campaign_id', $campaignId)->whereNull('withdrawn_at')->first();
    }
}
