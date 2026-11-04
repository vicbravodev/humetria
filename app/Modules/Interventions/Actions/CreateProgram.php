<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Actions;

use App\Actions\RecordAudit;
use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Interventions\Enums\ItemOrigin;
use App\Modules\Interventions\Enums\ItemScope;
use App\Modules\Interventions\Exceptions\ProgramUnavailable;
use App\Modules\Interventions\Models\Program;
use App\Modules\Results\Contracts\RiskResults;
use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Drafts the Programa de intervención of a closed evaluation (num. 8.3; criterio C-30): a row for each place the results put
 * in Medio or more, read from Results with the minimum applied. With nobody in Medio or more the norm asks for no Programa
 * and none is created (null). Under the minimum no figure may be derived, so the Programa starts with no rows and HR adds
 * the ones it wants. A second call finds the Programa and changes nothing.
 */
final readonly class CreateProgram
{
    public function __construct(private CurrentTenant $tenant, private CampaignRecords $campaigns, private RiskResults $results, private RecordAudit $audit) {}

    /**
     * @param  int|null  $userId  Who asked for it; null when the system drafted it as the evaluation closed.
     *
     * @throws ProgramUnavailable when the evaluation does not exist, still runs, or applies only the Guía I.
     */
    public function handle(string $campaignId, ?int $userId): ?Program
    {
        $campaign = $this->campaigns->find($campaignId) ?? throw new ProgramUnavailable;
        $risk = $this->results->forCampaign($campaign);

        if (in_array($risk->state, [RiskBreakdown::OPEN, RiskBreakdown::NOT_SCORED], true)) {
            throw new ProgramUnavailable;
        }

        $existing = Program::query()->where('campaign_id', $campaign->id)->first();
        $foci = $risk->needingProgram();

        if ($existing !== null || ($foci === [] && $risk->state === RiskBreakdown::AVAILABLE)) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($campaign, $risk, $foci, $userId): Program {
                $program = Program::query()->create([
                    'tenant_id' => $this->tenant->id(),
                    'campaign_id' => $campaign->id,
                    'highest_level' => $risk->highestNeedingProgram(),
                    'created_by' => $userId,
                ]);

                foreach ($foci as $focus) {
                    $program->items()->create([
                        'tenant_id' => $program->tenant_id,
                        'scope' => ItemScope::from($focus->scope),
                        'scope_key' => $focus->key,
                        'label' => $focus->label,
                        'category_key' => $focus->category,
                        'origin' => ItemOrigin::Results,
                        'highest_level' => $focus->level,
                    ]);
                }

                Log::info('intervention.program_drafted', ['program_id' => $program->id, 'campaign_id' => $campaign->id, 'rows' => count($foci), 'highest_level' => $program->highest_level?->value, 'by_system' => $userId === null]);
                $this->audit->handle('interventions.program_created', $program, ['campaign_id' => $campaign->id, 'by_system' => $userId === null]);

                return $program;
            });
        } catch (UniqueConstraintViolationException) {
            // Another request drafted it first: the Programa is that one.
            return Program::query()->where('campaign_id', $campaign->id)->firstOrFail();
        }
    }
}
