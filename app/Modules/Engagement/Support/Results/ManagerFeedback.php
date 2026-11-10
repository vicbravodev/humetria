<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Models\CommentSummary;
use App\Modules\Engagement\Models\Invitation;
use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Bank\ItemBank;

/**
 * The retroalimentación of one manager: the group of people who answered about them, if the minimums let it be shown. The
 * groups are the ones of the manager cut, so the group of a manager never contains another's.
 */
final class ManagerFeedback
{
    /** Strengths and opportunities a manager reads. */
    private const int HIGHLIGHTS = 2;

    /** The questions about the manager that the climate survey asks; with fewer, strengths and opportunities are not told. */
    private const int FULL = 8;

    /** `null` when the survey is not closed, the group is not shown or none of its questions reaches the minimum. */
    public static function for(Survey $survey, string $managerId): ?ManagerFeedbackData
    {
        $results = SurveyResults::for($survey);

        if (! $results->available()) {
            return null;
        }

        $rows = $results->managerGroups()->visible[$managerId] ?? null;

        if ($rows === null) {
            return null;
        }

        $company = $results->managerItems();
        $shown = array_values(array_filter($results->managerItems($rows), fn (ItemResult $item): bool => $item->shown));

        if ($shown === []) {
            return null;
        }

        // A wave that asked only some of the questions about the manager (a pulse) has no best or worst to pick.
        $highlights = count($company) < self::FULL ? ['strengths' => [], 'opportunities' => []] : Highlights::pick($shown, self::HIGHLIGHTS);
        $present = fn (ItemResult $item): array => self::present($item, $company[$item->key] ?? null);
        $summary = CommentSummary::query()->where('survey_id', $survey->id)->where('manager_group_id', $managerId)->first();

        return new ManagerFeedbackData(
            array_map($present, $shown),
            array_map($present, $highlights['strengths']),
            array_map($present, $highlights['opportunities']),
            // Someone of the group has another direct manager: it is the whole team below the manager, not only their reports.
            Invitation::query()->where('survey_id', $survey->id)->where('manager_group_id', $managerId)->whereNotNull('about_manager_id')->where('about_manager_id', '!=', $managerId)->exists(),
            $summary?->status,
            $summary?->status === SummaryStatus::Ready ? $summary->themes : null,
        );
    }

    /**
     * @return array{key: string, text: string, favorable: int, vsCompany: int|null}
     */
    private static function present(ItemResult $item, ?ItemResult $company): array
    {
        return [
            'key' => $item->key,
            'text' => ItemBank::find($item->key)->text,
            'favorable' => $item->favorable ?? 0,
            'vsCompany' => $company === null ? null : WaveComparison::againstCompany($item, $company),
        ];
    }
}
