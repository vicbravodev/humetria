<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Support\ProcedureModel;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Cómo se atiende» (board V4-Menu-Roles, the menu of whoever handles complaints): the procedure of the channel and the
 * deadlines the company set, read-only, for any person who may handle complaints. Nothing of a case.
 */
final class ProcedureController extends Controller
{
    public function __invoke(mixed $tenant): Response
    {
        Gate::authorize('handle-complaints');
        $channel = ComplaintChannel::query()->first();

        return Inertia::render('complaints/procedure', [
            'procedure' => $channel->procedure ?? ProcedureModel::text(),
            'acknowledgeWithinDays' => $channel->acknowledge_within_days ?? config()->integer('complaints.deadlines.acknowledge_days'),
            'resolveWithinDays' => $channel->resolve_within_days ?? config()->integer('complaints.deadlines.resolve_days'),
            'retentionMonths' => $channel->retention_months ?? config()->integer('complaints.retention.default_months'),
        ]);
    }
}
