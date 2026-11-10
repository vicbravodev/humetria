<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Support\LeaveDefaults;
use App\Modules\Leave\Support\RecordPages;
use App\Modules\Organization\Contracts\Roster;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The record of one person: owner, hr and viewer read it, whoever left included. A person that is not of the company, or
 * does not exist, is a 404.
 */
final readonly class EmployeeRecordController
{
    public function show(mixed $tenant, string $employee, Roster $roster, LeaveDefaults $defaults, RecordPages $pages): Response
    {
        Gate::authorize('view-vacations');
        $defaults->ensure();
        $person = $roster->find($employee) ?? abort(404);

        return Inertia::render('leave/employee', $pages->show($person, Gate::allows('manage-vacations')));
    }
}
