<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Pulse;

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Enums\SurveyKind;
use App\Modules\Engagement\Enums\SurveyStatus;
use App\Modules\Engagement\Models\Survey;

/** The drivers the latest closed climate survey of the company told it to act on first, in its order. */
final class PriorityDrivers
{
    /**
     * @return list<Driver>
     */
    public static function ofLatestClimate(): array
    {
        $climate = Survey::query()->where('kind', SurveyKind::Climate)->where('status', SurveyStatus::Closed)->orderByDesc('closed_at')->orderByDesc('id')->first();

        if ($climate === null) {
            return [];
        }

        return array_map(fn (array $priority): Driver => Driver::from($priority['driver']), $climate->priorities ?? []);
    }
}
