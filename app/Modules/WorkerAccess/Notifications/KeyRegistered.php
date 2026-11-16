<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Notifications;

use App\Modules\Billing\Contracts\MailBag;
use App\Modules\Tenancy\Data\CompanySummary;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a person that a key was registered to them, with its name and when (in local time), and «No fui yo» on a signed address
 * that revokes it and ends their sessions. From their company and in «usted». Queued: a mail server that fails must not fail
 * the registration; the signed address only lets the key be revoked.
 */
final class KeyRegistered extends Notification implements MailBag, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(private readonly string $label, private readonly CarbonImmutable $at, private readonly string $name, private readonly CompanySummary $company, private readonly string $notMeUrl) {}

    /** @return list<int> Seconds before the second and the third try. */
    public function backoff(): array
    {
        return [60, 300];
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
        $when = $this->at->setTimezone(config()->string('app.display_timezone'))->isoFormat('D [de] MMMM [de] YYYY [a las] H:mm');

        return (new MailMessage)->markdown('notifications::email', ['company' => $this->company, 'formal' => true])
            ->subject('Se registró una llave de acceso a su portal')
            ->greeting("Hola, {$this->name}")
            // Only plain characters of the label reach the markdown line (the request's rule): never a link.
            ->line("Se registró la llave «{$this->label}» el {$when}. Con ella se entra a su portal y se marca su jornada.")
            ->line('Si fue usted, no tiene que hacer nada. Si no fue usted, anúlela ahora:')
            ->action('No fui yo', $this->notMeUrl)
            ->line('Al anularla se cierran sus sesiones y avisamos a Recursos Humanos.');
    }

    public function mailBag(): string
    {
        return MailBag::ESSENTIAL;
    }
}
