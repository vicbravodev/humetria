<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Notifications;

use App\Modules\Tenancy\Enums\TenantRole;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * The email that carries the one-time link to join a company with a role. The plain token lives only here and in the link
 * (and in the queued job, encrypted, so neither Redis nor the Horizon dashboard shows it).
 */
final class InvitationEmail extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $tradeName,
        private readonly string $email,
        private readonly string $token,
        private readonly CarbonImmutable $expiresAt,
        private readonly TenantRole $role,
        private readonly ?string $inviter,
        private readonly int $workCenters,
        private readonly int $people,
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
        $subject = $this->inviter === null
            ? "Te invitaron a {$this->tradeName} en Humetría"
            : Str::before($this->inviter, ' ')." te invitó a {$this->tradeName} en Humetría";
        $expiresOn = $this->expiresAt->setTimezone(config()->string('app.display_timezone'));

        return (new MailMessage)
            ->subject($subject)
            ->action('Aceptar invitación', route('invitations.show', $this->token))
            ->markdown('mail.tenancy.invitation', [
                'tradeName' => $this->tradeName,
                'email' => $this->email,
                'inviter' => $this->inviter,
                'role' => $this->role->label(),
                'does' => match ($this->role) {
                    TenantRole::Owner => 'administrarás la cuenta, sus miembros y todo lo que RH lleva: NOM-035, NOM-037 y Ley Silla.',
                    TenantRole::Hr => 'podrás dar de alta centros de trabajo y empleados, lanzar evaluaciones y ver sus resultados.',
                    TenantRole::Committee => 'podrás revisar y validar los lugares de trabajo de la NOM-037 (teletrabajo) de sus centros.',
                    TenantRole::Viewer => 'podrás ver sus centros de trabajo y, cuando haya evaluaciones, sus resultados.',
                },
                'initials' => self::initials($this->tradeName),
                'size' => $this->size(),
                'expiresOn' => $expiresOn->isoFormat('D [de] MMMM'),
                'days' => (int) round(now()->diffInDays($this->expiresAt)),
            ]);
    }

    private function size(): string
    {
        if ($this->workCenters === 0) {
            return 'Aún sin centros de trabajo';
        }

        return trans_choice('{1} :count centro de trabajo|[2,*] :count centros de trabajo', $this->workCenters)
            .' · '.trans_choice('{0} sin personas|{1} :count persona|[2,*] :count personas', $this->people);
    }

    /** "Autopartes Demo" → "AD": the first letters of its first two words. */
    private static function initials(string $name): string
    {
        $words = array_slice(preg_split('/\s+/', trim($name)) ?: [], 0, 2);

        return mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), $words)));
    }
}
