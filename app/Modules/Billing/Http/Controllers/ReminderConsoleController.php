<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Billing\Actions\SendBillingNotices;
use App\Modules\Billing\Http\Requests\SendRemindersRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

final class ReminderConsoleController extends Controller
{
    /** Reminds the chosen accounts what they owe past due, from the list of accounts: the superadmin's. */
    public function store(SendRemindersRequest $request, SendBillingNotices $notices): RedirectResponse
    {
        $sent = $notices->remind($request->accountIds());

        Inertia::flash('toast', $sent === 0
            ? ['type' => 'info', 'message' => 'Ninguna de las cuentas elegidas tiene saldo vencido sin recordatorio de hoy.']
            : ['type' => 'success', 'message' => $sent === 1 ? 'Recordatorio enviado a 1 cuenta.' : "Recordatorio enviado a {$sent} cuentas."]);

        return back();
    }
}
