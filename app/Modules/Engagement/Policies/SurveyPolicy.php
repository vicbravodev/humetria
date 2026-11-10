<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Policies;

use App\Models\User;
use App\Modules\Engagement\Models\Survey;

/** What a person may do with a survey depends on its kind: view-climate or view-pulse, manage-climate or manage-pulse. */
final class SurveyPolicy
{
    public function view(User $user, Survey $survey): bool
    {
        return $user->can($survey->kind->viewPermission());
    }

    public function manage(User $user, Survey $survey): bool
    {
        return $user->can($survey->kind->managePermission());
    }
}
