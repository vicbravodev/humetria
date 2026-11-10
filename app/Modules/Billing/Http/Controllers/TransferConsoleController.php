<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\PreviewTransfer;
use App\Modules\Billing\Actions\TransferCompany;
use App\Modules\Billing\Exceptions\InvalidTransfer;
use App\Modules\Billing\Http\Requests\TransferRequest;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class TransferConsoleController extends Controller
{
    /** What the move would do before it is done: the same form, answered with the preview instead of a redirect. */
    public function preview(TransferRequest $request, string $company, CompanyRegistry $companies, PreviewTransfer $preview): JsonResponse
    {
        abort_if($companies->find($company) === null, 404);

        try {
            return response()->json($preview->handle($company, $request->toData()));
        } catch (InvalidTransfer $exception) {
            throw ValidationException::withMessages([$exception->field => $exception->getMessage()]);
        }
    }

    public function store(TransferRequest $request, string $company, CompanyRegistry $companies, TransferCompany $transfer): RedirectResponse
    {
        abort_if($companies->find($company) === null, 404);

        try {
            $moved = $transfer->handle($company, $request->toData(), $request->user() ?? abort(401));
        } catch (InvalidTransfer $exception) {
            return back()->withErrors([$exception->field => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Empresa trasladada.']);

        return redirect()->route('billing.accounts.show', $moved->to_account_id ?? $moved->from_account_id);
    }
}
