<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Events;

use App\Modules\Engagement\Enums\SurveyKind;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * A survey stopped taking answers, by hand or because its last day ended. Dispatched once the closing is committed.
 */
final readonly class SurveyClosed implements ShouldDispatchAfterCommit
{
    public function __construct(public string $surveyId, public SurveyKind $kind) {}
}
