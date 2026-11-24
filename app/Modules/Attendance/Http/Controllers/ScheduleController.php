<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Actions\SaveSchedule;
use App\Modules\Attendance\Actions\SaveSchedules;
use App\Modules\Attendance\Exceptions\EmployeeNotAvailable;
use App\Modules\Attendance\Exceptions\ScheduleStartLocked;
use App\Modules\Attendance\Http\Requests\BulkScheduleRequest;
use App\Modules\Attendance\Http\Requests\ScheduleRequest;
use App\Modules\Attendance\Support\PanelScope;
use App\Modules\Attendance\Support\SchedulePages;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The people's agreed schedules (criterio 2.6): owner, hr and viewer read them, owner and hr set them. A person is named in
 * the URL by id and looked up in the company's roster: one not in it is a 404.
 */
final readonly class ScheduleController
{
    public function __construct(private WorkCenters $workCenters, private Roster $roster) {}

    public function index(Request $request, SchedulePages $pages): Response
    {
        Gate::authorize('view-attendance');

        return Inertia::render('attendance/schedules', $pages->index(
            PanelScope::center($this->workCenters, $request->string('center')->toString()),
            $request->string('status')->toString(),
            $request->string('q')->trim()->limit(100, '')->toString(),
        ));
    }

    /**
     * The people who have a schedule, by name, each with it: what RH copies into another person's («Igual que …»).
     *
     * @return list<array{value: string, label: string, schedule: array<string, mixed>}>
     */
    public function options(Request $request, SchedulePages $pages): array
    {
        Gate::authorize('manage-attendance');

        return $pages->copies($request->string('q')->trim()->limit(100, '')->toString());
    }

    public function update(ScheduleRequest $request, mixed $tenant, string $employee, SaveSchedule $save): RedirectResponse
    {
        $person = $this->roster->find($employee) ?? abort(404);

        try {
            $save->handle($person->id, $request->shiftType(), $request->workMode(), $request->minutesByWeekday(), $request->startsByWeekday(), $request->validFrom());
        } catch (ScheduleStartLocked $locked) {
            throw $this->locked($locked);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Horario de {$person->name} guardado."]);

        return back();
    }

    /** The same schedule for each person chosen: all of them of the company or none (a 404, as for one). */
    public function bulk(BulkScheduleRequest $request, mixed $tenant, SaveSchedules $save): RedirectResponse
    {
        try {
            $people = $save->handle($request->employeeIds(), $request->shiftType(), $request->workMode(), $request->minutesByWeekday(), $request->startsByWeekday(), $request->validFrom());
        } catch (EmployeeNotAvailable) {
            abort(404);
        } catch (ScheduleStartLocked $locked) {
            throw $this->locked($locked);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => count($people) === 1 ? "Horario de {$people[0]->name} guardado." : 'Horario guardado para '.count($people).' personas.']);

        return back();
    }

    /** Why the date cannot be: the person already answered the week it falls in (or a later one), and the first Monday that can. */
    private function locked(ScheduleStartLocked $locked): ValidationException
    {
        $day = fn (CarbonImmutable $date): string => $date->isoFormat('dddd D [de] MMMM [de] YYYY');
        $name = $this->roster->find($locked->employeeId)->name ?? 'La persona';

        return ValidationException::withMessages(['valid_from' => "{$name} ya confirmó u objetó su registro hasta el {$day($locked->from->subDay())}: elige el {$day($locked->from)} o una fecha posterior."]);
    }
}
