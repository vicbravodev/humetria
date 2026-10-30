<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Http\Request;

/**
 * The company's work centers in use whose name has what was typed, for a field that searches them: a few at a time, by
 * name. Their names are no secret inside the company: any member may read them.
 */
final class WorkCenterOptionsController extends Controller
{
    public const int LIMIT = 20;

    /**
     * @return list<array{value: string, label: string}>
     */
    public function __invoke(Request $request): array
    {
        return array_values(WorkCenter::query()
            ->whereNull('archived_at')
            ->matching(['name'], $request->string('q')->trim()->limit(100, '')->toString())
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::LIMIT)
            ->get(['id', 'name'])
            ->map(fn (WorkCenter $workCenter): array => ['value' => $workCenter->id, 'label' => $workCenter->name])
            ->all());
    }
}
