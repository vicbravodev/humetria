<?php

declare(strict_types=1);

namespace App\Modules\Organization\Support;

use App\Modules\Organization\Contracts\Areas;
use App\Modules\Organization\Models\Area;

final class CompanyAreas implements Areas
{
    public function name(string $areaId): ?string
    {
        return Area::query()->find($areaId, ['id', 'name'])?->name;
    }

    public function names(array $areaIds): array
    {
        $names = [];

        foreach (Area::query()->whereIn('id', $areaIds)->get(['id', 'name']) as $area) {
            $names[$area->id] = $area->name;
        }

        return $names;
    }
}
