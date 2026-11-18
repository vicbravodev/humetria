<?php

declare(strict_types=1);

// Required from routes/web.php: the sign-in with a company's own identity provider. Public by nature (nobody is signed in yet),
// throttled per address, and the same refusal for everything that cannot go on.

use App\Modules\Sso\Http\Controllers\SsoSignInController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:sso')->prefix('admin/sso')->group(function (): void {
    Route::get('callback', [SsoSignInController::class, 'callback'])->name('sso.callback');
    Route::post('discover', [SsoSignInController::class, 'discover'])->name('sso.discover');
    Route::get('{tenant}', [SsoSignInController::class, 'start'])->where('tenant', '[a-z0-9-]+')->name('sso.start');
});
