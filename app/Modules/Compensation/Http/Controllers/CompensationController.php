<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Compensation\Actions\ListScenarios;
use App\Modules\Compensation\Support\BaseCatalog;
use App\Modules\Compensation\Support\CompensationStart;
use App\Modules\Compensation\Support\ContractStatus;
use App\Modules\Compensation\Support\ScenarioRows;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The start of Compensación (board V4-Comp-Inicio): the data of the contract with what each lacks, the active scenarios with
 * what each costs against today and what a point and a month of retroactivity cost; the first time, its three steps. A union
 * sees only its own account.
 */
final class CompensationController extends Controller
{
    public function index(ListScenarios $list, ScenarioRows $rows, ContractStatus $status, CompensationStart $start, BaseCatalog $catalog): Response
    {
        Gate::authorize('view-compensation');

        // The base benefits count from the start, not from the first time Prestaciones is opened.
        $catalog->ensure();

        $today = CarbonImmutable::now(config()->string('app.display_timezone'))->startOfDay();
        $contract = $status->handle($today);
        $now = $contract['roster']['people'] === 0 ? null : $start->today($today);

        return Inertia::render('compensation/index', [
            'status' => $contract,
            'first_time' => $contract['roster']['people'] === 0,
            'scenarios' => $rows->through($list->handle(false)),
            'counts' => $list->counts(),
            'today' => $now === null ? null : $now['today'],
            'negotiation' => $now === null || $now['today']['people'] === 0 ? null : $now['negotiation'],
        ]);
    }
}
