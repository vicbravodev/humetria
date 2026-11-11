<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support\Results;

use App\Modules\Engagement\Enums\Driver;
use App\Modules\Engagement\Support\Bank\ItemBank;

/** The ten drivers of the company block, each scored from the favorable share of its questions. */
final class DriverScores
{
    /**
     * @param  array<string, ItemResult>  $byKey
     * @return list<DriverResult>
     */
    public static function of(array $byKey): array
    {
        return array_map(function (Driver $driver) use ($byKey): DriverResult {
            $questions = ItemBank::ofDriver($driver);
            $items = [];
            foreach ($questions as $question) {
                if (isset($byKey[$question->key])) {
                    $items[] = $byKey[$question->key];
                }
            }

            $favorable = array_map(fn (ItemResult $item): ?int => $item->favorable, $items);
            $scored = ! $driver->descriptive() && count($items) === count($questions) && ! in_array(null, $favorable, true);

            return new DriverResult($driver, $driver->descriptive(), $scored ? (int) round(array_sum($favorable) / count($favorable)) : null, $items);
        }, Driver::cases());
    }
}
