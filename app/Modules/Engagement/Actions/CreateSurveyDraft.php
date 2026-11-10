<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions;

use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\ItemBank;
use App\Modules\Engagement\Support\Bank\Questionnaire;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Starts a survey draft with the questions of the bank. The loop question («¿se hizo algo con lo que se dijo?») only
 * makes sense once the company has closed a survey before, of any kind. A second open draft of the same kind is
 * refused by the database (one open survey per kind).
 */
final readonly class CreateSurveyDraft
{
    public function __construct(private CurrentTenant $tenant) {}

    public function handle(SurveyKind $kind, int $userId): Survey
    {
        $month = CarbonImmutable::now(config()->string('app.display_timezone'))->isoFormat('MMMM YYYY');
        $survey = Survey::query()->create([
            'tenant_id' => $this->tenant->id(),
            'kind' => $kind,
            'status' => SurveyStatus::Draft,
            'name' => "{$kind->label()} · {$month}",
            'bank_version' => ItemBank::VERSION,
            'items' => Questionnaire::climate(Survey::query()->where('status', SurveyStatus::Closed)->exists()),
            'created_by' => $userId,
        ]);

        Log::info('engagement.survey_drafted', ['survey_id' => $survey->id, 'kind' => $kind->value]);

        return $survey;
    }
}
