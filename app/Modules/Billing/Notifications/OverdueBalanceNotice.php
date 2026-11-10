<?php

declare(strict_types=1);

namespace App\Modules\Billing\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells an account that statements it was sent still have a balance after the days it has to pay. */
final class OverdueBalanceNotice extends Notification implements MailBag
{
    /** @param  list<array{company: string, period: string, balance: string}>  $lines */
    public function __construct(private readonly string $accountName, private readonly array $lines) {}

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Tiene un saldo vencido')
            ->greeting("Hola, {$this->accountName}")
            ->line('Estos estados de cuenta siguen con saldo:');

        foreach ($this->lines as $line) {
            $mail->line("{$line['company']}, {$line['period']}: {$line['balance']}");
        }

        return $mail->line('Si ya pagó, envíenos el comprobante para aplicarlo. Su servicio no se suspende por este aviso.');
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
