<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use SensitiveParameter;

/**
 * Fortify's password reset email, sent by a worker: who it is for, from where and when it was asked for (so the person
 * can tell whether it was them), and the link. Encrypted because the job carries the reset token.
 */
final class ResetPasswordNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $origin  "Chrome en macOS · Monterrey, Nuevo León · 30 oct, 14:02", never the address itself.
     */
    public function __construct(#[SensitiveParameter] string $token, public readonly ?string $origin = null)
    {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $minutes = config()->integer('auth.passwords.'.config()->string('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Crea una contraseña nueva')
            ->action('Crear contraseña nueva', $this->resetUrl($notifiable))
            ->markdown('mail.auth.reset-password', [
                'email' => $notifiable instanceof CanResetPassword ? $notifiable->getEmailForPasswordReset() : '',
                'minutes' => $minutes,
                'origin' => $this->origin,
            ]);
    }
}
