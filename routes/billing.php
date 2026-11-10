<?php

declare(strict_types=1);

use App\Modules\Billing\Actions\ExportReport;
use App\Modules\Billing\Http\Controllers\AccountCompaniesController;
use App\Modules\Billing\Http\Controllers\AccountConsoleController;
use App\Modules\Billing\Http\Controllers\AccountOptionsController;
use App\Modules\Billing\Http\Controllers\ChargeConsoleController;
use App\Modules\Billing\Http\Controllers\ExportConsoleController;
use App\Modules\Billing\Http\Controllers\LinkableCompaniesController;
use App\Modules\Billing\Http\Controllers\PaymentConsoleController;
use App\Modules\Billing\Http\Controllers\StatementConsoleController;
use App\Modules\Billing\Http\Controllers\SubscriptionConsoleController;
use App\Modules\Billing\Http\Controllers\SubscriptionPreviewController;
use App\Modules\Billing\Http\Controllers\TransferConsoleController;
use App\Modules\Billing\Http\Controllers\UsageGrantController;
use Illuminate\Support\Facades\Route;

// Who pays what, for the staff only: support reads it, the superadmin changes it (each controller checks its gate).
Route::get('admin/billing/accounts', [AccountConsoleController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('billing.accounts.index');

Route::post('admin/billing/accounts', [AccountConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('billing.accounts.store');

Route::get('admin/billing/accounts/search', AccountOptionsController::class)
    ->middleware(['auth', 'verified'])
    ->name('billing.accounts.search');

Route::get('admin/billing/accounts/{account}', [AccountConsoleController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('account')
    ->name('billing.accounts.show');

Route::put('admin/billing/accounts/{account}', [AccountConsoleController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('account')
    ->name('billing.accounts.update');

Route::post('admin/billing/accounts/{account}/companies', [AccountCompaniesController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('account')
    ->name('billing.accounts.companies.store');

Route::get('admin/billing/companies/search', LinkableCompaniesController::class)
    ->middleware(['auth', 'verified'])
    ->name('billing.companies.search');

Route::put('admin/billing/companies/{company}/subscription', [SubscriptionConsoleController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('company')
    ->name('billing.subscriptions.update');

Route::post('admin/billing/companies/{company}/subscription/preview', SubscriptionPreviewController::class)
    ->middleware(['auth', 'verified', 'throttle:billing-preview'])
    ->whereUuid('company')
    ->name('billing.subscriptions.preview');

// Moving a company between payers, from a cut, with the reason: it never gives or takes access.
Route::post('admin/billing/companies/{company}/transfer/preview', [TransferConsoleController::class, 'preview'])
    ->middleware(['auth', 'verified', 'throttle:billing-preview'])
    ->whereUuid('company')
    ->name('billing.transfers.preview');

Route::post('admin/billing/companies/{company}/transfer', [TransferConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('company')
    ->name('billing.transfers.store');

Route::post('admin/billing/companies/{company}/charges', [ChargeConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('company')
    ->name('billing.charges.store');

// Extra tokens of Humi for a company in the current month, with the reason: the superadmin's.
Route::post('admin/billing/companies/{company}/usage-grants', [UsageGrantController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('company')
    ->name('billing.usage.grant');

// Statements of account: read by the staff, made, issued and adjusted by the superadmin.
Route::get('admin/billing/statements/{statement}', [StatementConsoleController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('statement')
    ->name('billing.statements.show');

Route::get('admin/billing/statements/{statement}/print', [StatementConsoleController::class, 'print'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('statement')
    ->name('billing.statements.print');

Route::post('admin/billing/companies/{company}/statements/preview', [StatementConsoleController::class, 'preview'])
    ->middleware(['auth', 'verified', 'throttle:billing-preview'])
    ->whereUuid('company')
    ->name('billing.statements.preview');

Route::post('admin/billing/companies/{company}/statements', [StatementConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('company')
    ->name('billing.statements.store');

Route::post('admin/billing/statements/{statement}/issue', [StatementConsoleController::class, 'issue'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('statement')
    ->name('billing.statements.issue');

Route::delete('admin/billing/statements/{statement}', [StatementConsoleController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('statement')
    ->name('billing.statements.destroy');

Route::post('admin/billing/statements/{statement}/adjustments', [StatementConsoleController::class, 'adjust'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('statement')
    ->name('billing.statements.adjustments.store');

// Manual transfers (SPEI): recorded, applied and reversed by the superadmin; none of it touches a company's access.
Route::get('admin/billing/accounts/{account}/open-statements', [PaymentConsoleController::class, 'open'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('account')
    ->name('billing.accounts.open-statements');

Route::post('admin/billing/accounts/{account}/payments', [PaymentConsoleController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('account')
    ->name('billing.payments.store');

Route::post('admin/billing/payments/{payment}/allocations', [PaymentConsoleController::class, 'apply'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('payment')
    ->name('billing.payments.apply');

Route::post('admin/billing/payments/{payment}/reversal', [PaymentConsoleController::class, 'reverse'])
    ->middleware(['auth', 'verified'])
    ->whereUuid('payment')
    ->name('billing.payments.reverse');

// The three reports of the console as CSV (portfolio, balances, renewals): read by the staff, each download logged and audited.
Route::get('admin/billing/exports/{report}', [ExportConsoleController::class, 'show'])
    ->middleware(['auth', 'verified'])
    ->whereIn('report', ExportReport::REPORTS)
    ->name('billing.exports.show');
