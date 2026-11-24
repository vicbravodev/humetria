<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\EndRevokedImpersonation;
use App\Http\Middleware\ForbidWhileImpersonating;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Messaging\Http\Controllers\MetaWebhookController;
use App\Modules\Messaging\Http\Controllers\ResendWebhookController;
use App\Modules\Messaging\Http\Middleware\LimitWebhookBody;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// The webhooks of the providers (Meta, Resend): they have no account here, so the signature of the raw body is the credential
// (checked by each controller before it processes anything). No session and no cookie, hence no CSRF to forge. Throttled per
// address by `messaging-webhooks` (MessagingServiceProvider) and capped in size by LimitWebhookBody.
Route::withoutMiddleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
    ShareErrorsFromSession::class,
    PreventRequestForgery::class,
    EndRevokedImpersonation::class,
    AssignRequestContext::class,
    ForbidWhileImpersonating::class,
    HandleAppearance::class,
    HandleInertiaRequests::class,
])->middleware(['throttle:messaging-webhooks', LimitWebhookBody::class])->group(function (): void {
    Route::get('webhooks/whatsapp', [MetaWebhookController::class, 'verify'])->name('messaging.webhooks.meta.verify');
    Route::post('webhooks/whatsapp', [MetaWebhookController::class, 'receive'])->name('messaging.webhooks.meta.receive');
    Route::post('webhooks/resend', [ResendWebhookController::class, 'receive'])->name('messaging.webhooks.resend.receive');
});
