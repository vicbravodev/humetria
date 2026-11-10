<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\PulseMode;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Bank\PulseComposer;
use App\Modules\Engagement\Support\Pulse\PriorityDrivers;
use App\Modules\Engagement\Support\Pulse\PulseHistory;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Starts a pulse draft in one mode. The drivers to follow are the first of the latest closed climate survey; the tour
 * goes on from where the company left it. A second open pulse is refused by the database (one open survey per kind).
 */
final readonly class CreatePulseDraft
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @throws ValidationException when a follow-up is asked and no climate survey left priorities
     */
    public function handle(PulseMode $mode, int $userId): Survey
    {
        $prioritized = PriorityDrivers::ofLatestClimate();

        if ($mode === PulseMode::FollowUp && $prioritized === []) {
            throw ValidationException::withMessages(['mode' => 'Aún no hay prioridades de un clima para dar seguimiento.']);
        }

        $items = PulseComposer::compose(
            $mode,
            $prioritized,
            PulseHistory::tours(),
            PulseHistory::hasPreviousWave(),
        );
        $month = CarbonImmutable::now(config()->string('app.display_timezone'))->isoFormat('MMMM YYYY');
        $survey = Survey::query()->create([
            'tenant_id' => $this->tenant->id(),
            'kind' => SurveyKind::Pulse,
            'pulse_mode' => $mode,
            'status' => SurveyStatus::Draft,
            'name' => SurveyKind::Pulse->label()." · {$month}",
            'bank_version' => ItemBank::VERSION,
            'items' => $items,
            'created_by' => $userId,
        ]);

        Log::info('engagement.pulse_drafted', ['survey_id' => $survey->id, 'mode' => $mode->value, 'items' => count($items)]);

        return $survey;
    }
}
