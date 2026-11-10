<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Actions\SendPortalLinks;
use App\Modules\Leave\Http\Requests\SendLinksRequest;
use App\Modules\Leave\Support\BalancePages;
use App\Modules\Leave\Support\LeaveDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The days of everybody: owner, hr and viewer read them; owner and hr send people the link to their own vacations.
 */
final readonly class BalanceController
{
    public function index(Request $request, LeaveDefaults $defaults, BalancePages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();

        return Inertia::render('leave/balances', [
            ...$pages->index($request->string('tab')->toString(), $request->string('q')->trim()->limit(100, '')->toString()),
            'canManage' => Gate::allows('manage-vacations'),
        ]);
    }

    public function links(SendLinksRequest $request, SendPortalLinks $send): RedirectResponse
    {
        $sent = $send->handle($request->employeeIds(), $request->userId());
        Inertia::flash('toast', ['type' => 'success', 'message' => $sent === 1 ? 'Se envió 1 enlace.' : "Se enviaron {$sent} enlaces."]);

        return back();
    }
}
