<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Contracts\TeleworkStanding;
use App\Modules\Telework\Data\TeleworkRow;
use App\Modules\Tenancy\Contracts\WorkCenters;

final readonly class CompanyTeleworkStanding implements TeleworkStanding
{
    public function __construct(private ComplianceRows $rows, private WorkCenters $workCenters) {}

    public function forCenters(array $workCenterIds): array
    {
        $own = array_column($this->workCenters->all(), 'id');
        $rows = $this->rows->forMany(array_values(array_intersect($workCenterIds, $own)));

        return array_map(
            fn (array $center): array => array_map(
                fn (ComplianceRow $row): TeleworkRow => new TeleworkRow($row->numeral, $row->label, $row->state->value, $row->state->label(), $row->detail),
                $center,
            ),
            $rows,
        );
    }
}
