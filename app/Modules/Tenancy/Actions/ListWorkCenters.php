<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Actions;

use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\Eloquent\Collection;

final class ListWorkCenters
{
    /**
     * Every work center of the current company, archived ones included, by name.
     *
     * @return Collection<int, WorkCenter>
     */
    public function handle(): Collection
    {
        // ponytail: no pagination; a company has a handful of work centers. Paginate if one ever has hundreds.
        return WorkCenter::query()->orderBy('name')->get();
    }
}
