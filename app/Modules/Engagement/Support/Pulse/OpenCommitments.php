<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Pulse;

use App\Modules\Engagement\Enums\CommitmentStatus;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Commitment;
use App\Modules\Engagement\Models\Survey;

/**
 * What the last closed climate survey promised and is still open or in progress: how many, and the three whose date comes
 * first. It is what RH sees before launching the next survey, and what the heads-up of a scheduled pulse counts. Commitments
 * are only made after a climate survey, so a pulse draft shows these too.
 */
final class OpenCommitments
{
    /**
     * @return array{count: int, next: list<array{id: string, will_do: string, owner_name: string, due_on: string}>, surveyId: string}|null null when there is nothing open
     */
    public static function latest(): ?array
    {
        $climate = Survey::query()->where('kind', SurveyKind::Climate)->where('status', SurveyStatus::Closed)->orderByDesc('closed_at')->orderByDesc('id')->first();

        if ($climate === null) {
            return null;
        }

        $open = Commitment::query()->where('survey_id', $climate->id)->whereIn('status', [CommitmentStatus::Open, CommitmentStatus::InProgress]);
        $count = $open->count();

        if ($count === 0) {
            return null;
        }

        return [
            'count' => $count,
            'next' => array_values($open->orderBy('due_on')->orderBy('id')->limit(3)->get(['id', 'will_do', 'owner_name', 'due_on'])
                ->map(fn (Commitment $commitment): array => ['id' => $commitment->id, 'will_do' => $commitment->will_do, 'owner_name' => $commitment->owner_name, 'due_on' => $commitment->due_on->toDateString()])->all()),
            'surveyId' => $climate->id,
        ];
    }
}
