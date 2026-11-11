<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Models\Survey;
use App\Modules\Engagement\Support\Thresholds;
use App\Modules\Tenancy\Contracts\Companies;

/**
 * The table of `engagement:item-stats`, one line per question. It works inside the company of the survey, which the command
 * has no request to tell, and writes counts only: never a comment, nor a text of an answer.
 */
final readonly class ItemStatisticsReport
{
    public function __construct(private Companies $companies, private ItemStatistics $statistics, private Thresholds $thresholds) {}

    /**
     * @return list<string>|null null when no survey has that id
     */
    public function lines(string $surveyId): ?array
    {
        $found = Survey::query()->withoutGlobalScopes()->find($surveyId, ['id', 'tenant_id']);

        if ($found === null) {
            return null;
        }

        $this->companies->enter($found->tenant_id);
        $survey = Survey::query()->findOrFail($surveyId);
        $minutes = $this->statistics->minutes($survey);

        return [
            ...array_map($this->line(...), $this->statistics->of($survey)),
            $minutes === null ? 'Minutos: sin datos suficientes' : "Minutos: mediana {$minutes['median']}, percentil 90 {$minutes['p90']}",
        ];
    }

    /**
     * @param  array{key: string, presented: int, answered: int, skipped: int, favorable: int|null, midpoint: int|null, flags: list<string>}  $row
     */
    private function line(array $row): string
    {
        if (! $this->thresholds->met($row['answered'])) {
            return "{$row['key']}  presented={$row['presented']} skipped={$row['skipped']}";
        }

        $shares = $row['favorable'] === null || $row['midpoint'] === null ? '' : " favorable={$row['favorable']}% midpoint={$row['midpoint']}%";

        return "{$row['key']}  presented={$row['presented']} answered={$row['answered']} skipped={$row['skipped']}{$shares} flags=".($row['flags'] === [] ? '-' : implode(',', $row['flags']));
    }
}
