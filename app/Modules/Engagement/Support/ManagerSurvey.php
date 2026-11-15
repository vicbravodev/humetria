<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Survey;

/**
 * The survey a manager reads feedback from.
 */
final class ManagerSurvey
{
    /** The last closed survey, of any kind, that asked about the manager: a pulse that did not ask never replaces it. */
    public static function latest(): ?Survey
    {
        return Survey::query()->where('status', SurveyStatus::Closed)->latest('closed_at')->lazy()
            ->first(fn (Survey $survey): bool => array_filter($survey->items, fn (string $ref): bool => str_starts_with($ref, 'manager.')) !== []);
    }
}
