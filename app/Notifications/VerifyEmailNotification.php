<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Fortify's email verification, sent by a worker. The signed link is built when the worker sends it, so its expiry
 * counts from then.
 */
final class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifica tu correo en Humetría')
            ->action('Verificar correo', $this->verificationUrl($notifiable))
            ->markdown('mail.auth.verify-email', [
                'email' => $notifiable instanceof MustVerifyEmail ? $notifiable->getEmailForVerification() : '',
                'minutes' => config()->integer('auth.verification.expire', 60),
            ]);
    }
}
