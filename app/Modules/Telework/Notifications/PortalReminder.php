<?php

declare(strict_types=1);

namespace App\Modules\Telework\Notifications;

use App\Modules\Telework\Data\PendingChecklist;
use App\Modules\Telework\Data\PendingDocument;
use App\Modules\Telework\Data\PendingEquipment;
use App\Modules\Telework\Data\PendingPhotoRequest;
use App\Modules\Telework\Data\PendingVisit;
use App\Modules\Telework\Data\PortalPendingData;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an employee, as "usted", everything waiting in their portal, with their personal link (made as the job runs, so
 * the notification is sent at once and never queued: the plain token must not reach the queue).
 */
final class PortalReminder extends Notification
{
    public function __construct(
        private readonly string $url,
        private readonly string $name,
        private readonly string $company,
        private readonly PortalPendingData $pending,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject('Tiene pendientes en su portal')->greeting("Hola, {$this->name}")
            ->line("{$this->company} le dejó pendientes en su portal de teletrabajo:");

        $this->list($mail, 'Listas por contestar', array_map(fn (PendingChecklist $item): string => $item->workplace.' · Sección '.($item->section === 1 ? 'I' : 'II'), $this->pending->checklists));
        $this->list($mail, 'Documentos por confirmar', array_map(fn (PendingDocument $item): string => $item->title, $this->pending->documents));
        $this->list($mail, 'Equipo por confirmar', array_map(fn (PendingEquipment $item): string => $item->title, $this->pending->equipment));
        $this->list($mail, 'Fotos que pidió la Comisión', array_map(fn (PendingPhotoRequest $item): string => $item->workplace, $this->pending->photos));
        $this->list($mail, 'Autorizaciones de visita', array_map(fn (PendingVisit $item): string => $item->workplace, $this->pending->visits));

        return $mail->action('Abrir mi portal', $this->url)
            ->line('Este enlace es solo suyo: no lo comparta. Si su empresa le envía uno nuevo, el anterior deja de funcionar.');
    }

    /**
     * @param  list<string>  $items
     */
    private function list(MailMessage $mail, string $heading, array $items): void
    {
        if ($items === []) {
            return;
        }

        $mail->line("{$heading}:");

        foreach ($items as $item) {
            $mail->line("- {$item}");
        }
    }
}
