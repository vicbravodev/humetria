<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Fortify's email verification, sent by a worker. Same text and theme as Laravel's (lang/es.json); the signed link is
 * built when the worker sends it, so its expiry counts from then.
 */
final class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;
}
