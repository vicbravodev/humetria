<?php

declare(strict_types=1);

use App\Modules\Telework\Http\Controllers\TeleworkSettingsController;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}/telework.
// Every name starts with tenant.telework.: it is how the committee role (LimitCommitteeToTelework) is let in.
/** @var string $notReserved */
Route::get('admin/{tenant:slug}/telework/settings', [TeleworkSettingsController::class, 'edit'])->where('tenant', $notReserved)->name('tenant.telework.settings.edit');
Route::put('admin/{tenant:slug}/telework/settings', [TeleworkSettingsController::class, 'update'])->where('tenant', $notReserved)->name('tenant.telework.settings.update');
