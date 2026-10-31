<?php

declare(strict_types=1);

namespace App\Modules\Campaigns\Actions;

use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Instruments\Contracts\Nom035Guides;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Prepares a NOM-035 evaluation of a work center: its active headcount decides the guides (num. 2, 7.1), and with
 * Guía III RH may take a representative sample, drawn now so RH can review who is in it (III.1). Nobody is invited
 * until the draft is launched.
 */
final readonly class CreateEvaluationDraft
{
    public function __construct(
        private CurrentTenant $currentTenant,
        private WorkCenters $workCenters,
        private Roster $roster,
        private Nom035Guides $guides,
        private DrawSample $drawSample,
    ) {}

    /**
     * @throws ValidationException when the work center cannot be evaluated now, or a sample was asked where Guía III
     *                             does not apply.
     */
    public function handle(string $workCenterId, Population $population, int $createdBy): Campaign
    {
        try {
            return DB::transaction(function () use ($workCenterId, $population, $createdBy): Campaign {
                // Under lock on the work center: archiving it at the same time waits here, or this finds it archived.
                $this->assertActiveWorkCenter($workCenterId);
                $workers = count($this->roster->activeIn($workCenterId));

                if ($workers === 0) {
                    throw ValidationException::withMessages(['work_center_id' => 'Este centro no tiene personas activas. Da de alta a su personal primero.']);
                }

                $instruments = $this->guides->instruments($workers);

                if ($population === Population::Sample && ! in_array('nom035.guide-iii', $instruments, true)) {
                    throw ValidationException::withMessages(['population' => 'Solo la Guía III se puede aplicar a una muestra; este centro tiene 50 personas o menos.']);
                }

                $campaign = Campaign::query()->create([
                    'tenant_id' => $this->currentTenant->id(),
                    'work_center_id' => $workCenterId,
                    'kind' => CampaignKind::Nom035Evaluation,
                    'status' => CampaignStatus::Draft,
                    'instruments' => $instruments,
                    'workers' => $workers,
                    'population' => $population,
                    'created_by' => $createdBy,
                ]);

                if ($population === Population::Sample) {
                    $this->drawSample->handle($campaign);
                }

                Log::info('campaign.drafted', ['campaign_id' => $campaign->id, 'work_center_id' => $workCenterId, 'workers' => $workers]);

                return $campaign;
            });
        } catch (UniqueConstraintViolationException) {
            // Another draft or active evaluation of the same work center won the race.
            throw $this->alreadyOpen();
        }
    }

    private function assertActiveWorkCenter(string $workCenterId): void
    {
        if ($this->workCenters->lockActive($workCenterId) === null) {
            throw ValidationException::withMessages(['work_center_id' => 'Elige un centro de trabajo activo.']);
        }

        $open = Campaign::query()
            ->where('work_center_id', $workCenterId)
            ->where('kind', CampaignKind::Nom035Evaluation)
            ->whereIn('status', [CampaignStatus::Draft, CampaignStatus::Active])
            ->exists();

        if ($open) {
            throw $this->alreadyOpen();
        }
    }

    private function alreadyOpen(): ValidationException
    {
        return ValidationException::withMessages(['work_center_id' => 'Este centro ya tiene una evaluación en curso o en borrador.']);
    }
}
