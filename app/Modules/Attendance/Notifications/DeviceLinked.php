<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Notifications;

use App\Modules\Attendance\Support\DeviceLabel;
use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that a device was linked to their time record, with its name and when (in local time), so they notice
 * one that was not them. From their company and in «usted». Queued: it carries no secret, and a mail server that fails
 * must not fail the linking.
 */
final class DeviceLinked extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $label, private readonly CarbonImmutable $at, private readonly string $name, private readonly CompanySummary $company) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->at->setTimezone(config()->string('app.display_timezone'))->isoFormat('D [de] MMMM [de] YYYY [a las] H:mm');

        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => true])
            ->subject('Se vinculó un dispositivo a su registro de jornada')
            ->greeting("Hola, {$this->name}")
            // Only plain characters of the label reach the markdown line: never a link.
            ->line('Se vinculó un dispositivo «'.DeviceLabel::clean($this->label)."» el {$when}.")
            // The person's own page of the time record (Marcar), where they mark with the linked device.
            ->action('Abrir mi registro', rtrim(config()->string('app.url'), '/')."/a/{$this->company->slug}")
            ->line('Si no fue usted, avise a Recursos Humanos.');
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
