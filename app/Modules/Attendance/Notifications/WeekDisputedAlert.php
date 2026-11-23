<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells RH, as "tú", that a person disputed their week (criterio 5.2): who, where and which week, and where to resolve it.
 * Never the reason: that is read in Humetría.
 */
final class WeekDisputedAlert extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CompanySummary $company,
        private readonly string $person,
        private readonly string $workCenter,
        private readonly CarbonImmutable $monday,
        private readonly string $url,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $where = $this->workCenter === '' ? '' : " · {$this->workCenter}";

        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company])
            ->subject("{$this->person} objetó su semana")
            ->greeting('Una semana objetada espera')
            ->line("{$this->person}{$where} · semana {$this->week()}")
            ->line('Revisa su motivo y resuélvela con una nota: la semana vuelve a la persona para que la confirme o la objete otra vez.')
            ->action('Revisar objeción', $this->url);
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }

    /** «del 2 al 8 de noviembre de 2026», or «del 30 de noviembre al 6 de diciembre de 2026» across months. */
    private function week(): string
    {
        $monday = $this->monday;
        $sunday = $monday->addDays(6);

        return $monday->month === $sunday->month
            ? "del {$monday->day} al {$sunday->isoFormat('D [de] MMMM [de] YYYY')}"
            : "del {$monday->isoFormat('D [de] MMMM')} al {$sunday->isoFormat('D [de] MMMM [de] YYYY')}";
    }
}
