<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Announcements\Actions\EndMaintenance;
use App\Modules\Announcements\Actions\StartMaintenance;
use App\Modules\Announcements\Http\Requests\EndMaintenanceRequest;
use App\Modules\Announcements\Http\Requests\StartMaintenanceRequest;
use Illuminate\Http\RedirectResponse;

/** The platform's maintenance: every company's panel in read only while it lasts. */
final class MaintenanceController extends Controller
{
    public function store(StartMaintenanceRequest $request, StartMaintenance $start): RedirectResponse
    {
        $start->handle($request->reason());

        return back()->with('status', 'Plataforma en mantenimiento.');
    }

    public function destroy(EndMaintenanceRequest $request, EndMaintenance $end): RedirectResponse
    {
        $end->handle();

        return back()->with('status', 'Mantenimiento terminado.');
    }
}
