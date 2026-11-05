<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Support;

use App\Modules\Compensation\Data\FiscalSnapshot;
use App\Modules\Compensation\Models\FiscalParameter;
use App\Modules\Compensation\Support\Calculation\Rate;
use Carbon\CarbonImmutable;

/**
 * Reads the platform's fiscal parameters as of a date. For each key (and state) the value with the latest start on or
 * before the date rules; if its vigencia already ended and nothing replaced it, it is carried and flagged stale.
 */
final class FiscalParameters
{
    public function at(CarbonImmutable $date): FiscalSnapshot
    {
        $day = $date->toDateString();
        $values = [];
        $stale = [];

        $rows = FiscalParameter::query()
            ->where('valid_from', '<=', $day)
            ->orderBy('valid_from')
            ->get(['key', 'state', 'valid_from', 'valid_to', 'value_int', 'scale']);

        foreach ($rows as $row) {
            $id = $row->state === null ? $row->key : "{$row->key}:{$row->state}";
            $values[$id] = Rate::scaled($row->value_int, $row->scale);

            if ($row->valid_to !== null && $row->valid_to->toDateString() < $day) {
                $stale[$id] = $id;
            } else {
                unset($stale[$id]);
            }
        }

        sort($stale);

        return new FiscalSnapshot($values, $stale);
    }
}
