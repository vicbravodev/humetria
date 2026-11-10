<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Support\StatementSheet;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The annual statement (LFT art. 81) of a person as RH prints it, with whether they confirmed they received it. Owner, hr
 * and viewer read it; one of another company or made up is a 404.
 */
final readonly class StatementController
{
    public function show(mixed $tenant, string $statement, StatementSheet $sheet): Response
    {
        Gate::authorize('view-vacations');
        $own = LeaveStatement::query()->findOrFail($statement);

        return Inertia::render('leave/statement', ['employeeId' => $own->employee_id, 'sheet' => $sheet->for($own)]);
    }
}
