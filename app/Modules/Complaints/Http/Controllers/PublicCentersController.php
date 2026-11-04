<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Http\Request;

/**
 * The work centers whose name has what was typed, for the form's field that searches them. A company's centers are not a
 * secret to the people who work in it, and the code in the address is the credential of the company.
 */
final class PublicCentersController extends Controller
{
    public const int LIMIT = 20;

    /**
     * @return list<array{value: string, label: string}>
     */
    public function __invoke(Request $request, WorkCenters $centers): array
    {
        $found = $centers->search($request->string('q')->trim()->limit(100, '')->toString(), self::LIMIT);

        return array_map(fn (WorkCenterSummary $center): array => ['value' => $center->id, 'label' => $center->name], $found);
    }
}
