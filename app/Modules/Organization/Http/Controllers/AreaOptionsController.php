<?php

declare(strict_types=1);

namespace App\Modules\Organization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Models\Area;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The company's areas whose name has what was typed, for a field that searches them: a few at a time, by name. Area
 * names are no secret inside the company: any member may read them.
 */
final class AreaOptionsController extends Controller
{
    public const int LIMIT = 20;

    /**
     * @return list<array{value: string, label: string}>
     */
    public function __invoke(Request $request): array
    {
        $search = $request->string('q')->trim()->limit(100, '')->toString();
        // LIKE's own wildcards in what was typed are searched as themselves.
        $like = '%'.addcslashes($search, '\\%_').'%';

        return array_values(Area::query()
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'ilike', $like))
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name'])
            ->map(fn (Area $area): array => ['value' => $area->id, 'label' => $area->name])
            ->all());
    }
}
