<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Contracts\UsageMeter;
use App\Modules\Billing\Data\UsageHistoryRow;
use App\Modules\Billing\Data\UsageSnapshot;
use App\Modules\Tenancy\Actions\GrantModule;
use App\Modules\Tenancy\Actions\RevokeModule;
use App\Modules\Tenancy\Contracts\CompanyLimits;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Data\LimitUsage;
use App\Modules\Tenancy\Data\ModuleRow;
use App\Modules\Tenancy\Exceptions\InvalidModuleChange;
use App\Modules\Tenancy\Http\Requests\GrantModuleRequest;
use App\Modules\Tenancy\Http\Requests\RevokeModuleRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** One company in the console, with its modules and what it has used of its plan's limits: the staff reads them, the superadmin grants and takes them away by hand. */
final class CompanyModulesController extends Controller
{
    public function show(Tenant $tenant, CompanyModules $modules, CompanyLimits $limits, UsageMeter $usage): Response
    {
        Gate::authorize('view-console');

        $status = $tenant->status();

        return Inertia::render('tenancy/company', [
            'company' => [
                'id' => $tenant->id,
                'legal_name' => $tenant->legal_name,
                'trade_name' => $tenant->trade_name,
                'rfc' => $tenant->rfc,
                'slug' => $tenant->slug,
                'status' => $status->value,
                'status_label' => $status->label(),
                'restriction_reason' => $tenant->restriction_reason,
            ],
            'modules' => array_map(fn (ModuleRow $row): array => [
                'key' => $row->key,
                'label' => $row->label,
                'enabled' => $row->enabled,
                'source' => $row->source,
                'source_label' => $row->sourceLabel,
                'reason' => $row->reason,
                'ends_on' => $row->endsOn,
                'exists_in_code' => $row->existsInCode,
            ], $modules->list($tenant->id)),
            'limits' => array_map(fn (LimitUsage $usage): array => [
                'resource' => $usage->resource,
                'label' => $usage->label,
                'used' => $usage->used,
                'amount' => $usage->amount,
                'remaining' => $usage->remaining(),
                'over' => $usage->over,
            ], $limits->usage($tenant->id)),
            // Humi's tokens this month against the quota (plan plus grants), and the months before, a page at a time.
            'usage' => $this->usage($usage->snapshot($tenant->id, UsageMeter::ASSISTANT_TOKENS)),
            'usage_history' => $usage->history($tenant->id, UsageMeter::ASSISTANT_TOKENS)->through(fn (UsageHistoryRow $row): array => [
                'period' => $row->periodStart,
                'label' => $row->periodLabel,
                'used' => $row->used,
                'quota' => $row->quota,
            ]),
            'can_manage' => Gate::allows('manage-tenants'),
        ]);
    }

    /**
     * @return array{period_label: string, used: int, reserved: int, quota: int, granted: int, remaining: int}
     */
    private function usage(UsageSnapshot $snapshot): array
    {
        return [
            'period_label' => $snapshot->periodLabel,
            'used' => $snapshot->used,
            'reserved' => $snapshot->reserved,
            'quota' => $snapshot->quota,
            'granted' => $snapshot->granted,
            'remaining' => $snapshot->remaining(),
        ];
    }

    public function grant(GrantModuleRequest $request, Tenant $tenant, GrantModule $grant): RedirectResponse
    {
        try {
            $grant->handle($tenant, $request->string('module')->toString(), $request->string('reason')->toString(), $request->until(), $request->user() ?? abort(401));
        } catch (InvalidModuleChange $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Módulo concedido.']);

        return redirect()->route('companies.show', $tenant);
    }

    public function revoke(RevokeModuleRequest $request, Tenant $tenant, RevokeModule $revoke): RedirectResponse
    {
        try {
            $revoke->handle($tenant, $request->string('module')->toString(), $request->string('reason')->toString(), $request->user() ?? abort(401));
        } catch (InvalidModuleChange $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Módulo quitado.']);

        return redirect()->route('companies.show', $tenant);
    }
}
