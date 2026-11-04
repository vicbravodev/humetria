<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * The work centers whose name has what was typed, for the inbox's filter. The handler roles (the Comisión, the outside
 * handler) may not use the company's own search, which is behind pages they do not see.
 */
final class CenterOptionsController extends Controller
{
    private const int LIMIT = 20;

    /**
     * @return list<array{value: string, label: string}>
     */
    public function __invoke(Request $request, WorkCenters $centers): array
    {
        Gate::authorize('handle-complaints');
        $found = $centers->search($request->string('q')->trim()->limit(100, '')->toString(), self::LIMIT);

        return array_map(fn (WorkCenterSummary $center): array => ['value' => $center->id, 'label' => $center->name], $found);
    }
}
