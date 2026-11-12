<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\AdjustStatement;
use App\Modules\Billing\Actions\BuildStatement;
use App\Modules\Billing\Actions\DescribeStatement;
use App\Modules\Billing\Actions\DiscardStatement;
use App\Modules\Billing\Actions\GenerateStatement;
use App\Modules\Billing\Actions\IssueStatement;
use App\Modules\Billing\Exceptions\InvalidStatement;
use App\Modules\Billing\Http\Requests\AdjustStatementRequest;
use App\Modules\Billing\Http\Requests\StatementPeriodRequest;
use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Support\Money;
use App\Modules\Billing\Support\ShortDate;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class StatementConsoleController extends Controller
{
    public function show(Statement $statement, DescribeStatement $describe): Response
    {
        Gate::authorize('view-billing');

        return Inertia::render('billing/statement', [...$describe->handle($statement), 'can_manage' => Gate::allows('manage-billing')]);
    }

    /** The same statement as plain HTML with print styles, for the browser to print or save as PDF. */
    public function print(Statement $statement, DescribeStatement $describe): View
    {
        Gate::authorize('view-billing');

        return view('billing.statement-print', $describe->handle($statement));
    }

    /** The lines of a month before the draft is made: the same work, nothing written. */
    public function preview(StatementPeriodRequest $request, string $company, CompanyRegistry $companies, BuildStatement $build): JsonResponse
    {
        abort_if($companies->find($company) === null, 404);

        try {
            $draft = $build->handle($company, $request->month());
        } catch (InvalidStatement $exception) {
            throw ValidationException::withMessages(['period' => $exception->getMessage()]);
        }

        return response()->json([
            'period' => ShortDate::month($draft->periodStart),
            'lines' => array_map(fn ($line): array => ['label' => $line->label, 'amount' => Money::format($line->amountCents), 'explanation' => $line->explanation], $draft->lines),
            'subtotal' => Money::format($draft->subtotalCents),
            'vat' => Money::format($draft->vatCents),
            'total' => Money::format($draft->totalCents),
        ]);
    }

    public function store(StatementPeriodRequest $request, string $company, CompanyRegistry $companies, GenerateStatement $generate): RedirectResponse
    {
        abort_if($companies->find($company) === null, 404);

        try {
            $statement = $generate->handle($company, $request->month(), $request->user() ?? abort(401));
        } catch (InvalidStatement $exception) {
            return back()->withErrors(['period' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estado de cuenta generado.']);

        return redirect()->route('billing.statements.show', $statement);
    }

    public function issue(Statement $statement, IssueStatement $issue): RedirectResponse
    {
        Gate::authorize('manage-billing');

        try {
            $issue->handle($statement, request()->user() ?? abort(401));
        } catch (InvalidStatement $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Estado de cuenta emitido.']);

        return redirect()->route('billing.statements.show', $statement);
    }

    public function adjust(AdjustStatementRequest $request, Statement $statement, AdjustStatement $adjust): RedirectResponse
    {
        try {
            $adjust->handle($statement, $request->toData(), $request->user() ?? abort(401));
        } catch (InvalidStatement $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ajuste emitido.']);

        return redirect()->route('billing.statements.show', $statement);
    }

    /** A draft that should not exist goes away with its lines; what it billed once is billed again by the next one. */
    public function destroy(Statement $statement, DiscardStatement $discard): RedirectResponse
    {
        Gate::authorize('manage-billing');

        try {
            $discard->handle($statement);
        } catch (InvalidStatement $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Borrador descartado.']);

        return redirect()->route('billing.accounts.show', $statement->billing_account_id);
    }
}
