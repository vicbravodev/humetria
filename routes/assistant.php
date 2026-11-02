<?php

declare(strict_types=1);

use App\Modules\Assistant\Http\Controllers\ConversationController;
use App\Modules\Assistant\Http\Middleware\AllowAssistant;
use Illuminate\Support\Facades\Route;

// Required from routes/company.php, inside the company group: these routes live under /admin/{tenant}.
/** @var string $notReserved */
Route::middleware(AllowAssistant::class)->group(function () use ($notReserved): void {
    Route::get('admin/{tenant:slug}/assistant/conversations', [ConversationController::class, 'index'])->where('tenant', $notReserved)->name('tenant.assistant.conversations.index');
    Route::get('admin/{tenant:slug}/assistant/conversations/{conversation}', [ConversationController::class, 'show'])->where('tenant', $notReserved)->whereUuid('conversation')->name('tenant.assistant.conversations.show');
    Route::delete('admin/{tenant:slug}/assistant/conversations/{conversation}', [ConversationController::class, 'destroy'])->where('tenant', $notReserved)->whereUuid('conversation')->name('tenant.assistant.conversations.destroy');
});
