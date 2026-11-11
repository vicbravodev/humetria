<?php

declare(strict_types=1);

namespace App\Modules\Leave\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Actions\AcknowledgeStatement;
use App\Modules\Leave\Models\LeaveStatement;
use App\Modules\Leave\Support\PortalVisitor;
use App\Modules\Leave\Support\StatementSheet;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The annual statement of a period (LFT art. 81) in the portal of the person it is about, who confirms they received it.
 * Somebody else's statement does not exist.
 */
final class PortalStatementController extends Controller
{
    public function show(string $token, string $statement, PortalVisitor $visitor, StatementSheet $sheet): Response
    {
        $person = $visitor->open($token);
        $own = LeaveStatement::query()->where('employee_id', $person->employeeId)->findOrFail($statement);

        return Inertia::render('leave/portal-statement', [
            'token' => $token,
            'company' => $person->company,
            'statement' => ['id' => $own->id],
            'sheet' => $sheet->for($own),
        ]);
    }

    public function acknowledge(string $token, string $statement, PortalVisitor $visitor, AcknowledgeStatement $acknowledge): RedirectResponse
    {
        $acknowledge->handle($visitor->open($token)->employeeId, $statement);

        return to_route('portal.vacations.statements.show', [$token, $statement]);
    }
}
