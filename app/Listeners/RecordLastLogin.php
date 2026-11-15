<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Context;

/**
 * Remembers when a person last signed in. The superadmin entering as an owner, and coming back, is not that person signing in:
 * Impersonation marks those two logins and they are not recorded.
 */
final class RecordLastLogin
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User && Context::get('impersonation_switch') !== true) {
            $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }
}
