<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Actions\SaveHoliday;
use App\Modules\Leave\Actions\SaveLeaveType;
use App\Modules\Leave\Actions\SaveWorkWeek;
use App\Modules\Leave\Http\Requests\HolidayRequest;
use App\Modules\Leave\Http\Requests\LeaveTypeRequest;
use App\Modules\Leave\Http\Requests\WorkWeekRequest;
use App\Modules\Leave\Models\LeaveHoliday;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\SettingsPages;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What RH adjusts of Vacaciones: owner, hr and viewer read it; owner and hr change the leave types, the holidays and the
 * working week of the law. A type or a holiday of another company is a 404.
 */
final readonly class SettingsController
{
    public function show(Request $request, LeaveDefaults $defaults, SettingsPages $pages): Response
    {
        Gate::authorize('view-vacations');

        return Inertia::render('leave/settings', [
            ...$pages->show($request->string('tab')->toString(), $defaults->ensure()),
            'canManage' => Gate::allows('manage-vacations'),
        ]);
    }

    public function update(WorkWeekRequest $request, SaveWorkWeek $save): RedirectResponse
    {
        $save->handle($request->week());

        return $this->done('Semana laboral guardada.');
    }

    public function storeType(LeaveTypeRequest $request, SaveLeaveType $save): RedirectResponse
    {
        $save->handle($request->name(), $request->paid());

        return $this->done('Permiso agregado.');
    }

    public function updateType(LeaveTypeRequest $request, mixed $tenant, string $type, SaveLeaveType $save): RedirectResponse
    {
        $save->handle($request->name(), $request->paid(), LeaveType::query()->findOrFail($type)->id);

        return $this->done('Permiso guardado.');
    }

    public function archiveType(mixed $tenant, string $type, SaveLeaveType $save): RedirectResponse
    {
        Gate::authorize('manage-vacations');
        $save->archive(LeaveType::query()->findOrFail($type)->id);

        return $this->done('Permiso archivado. Las solicitudes ya hechas lo conservan.');
    }

    public function storeHoliday(HolidayRequest $request, SaveHoliday $save): RedirectResponse
    {
        $save->handle($request->day(), $request->name());

        return $this->done('Festivo agregado.');
    }

    public function destroyHoliday(mixed $tenant, string $holiday, SaveHoliday $save): RedirectResponse
    {
        Gate::authorize('manage-vacations');
        $save->remove(LeaveHoliday::query()->findOrFail($holiday)->id);

        return $this->done('Festivo quitado. Las solicitudes ya hechas no cambian.');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
