<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Complaints\Contracts\ComplaintActivity;
use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;

/** Denuncias: the public channel's QR, a complaint from the audience (or the presenter's screen), and RH acknowledging it. */
final readonly class ComplaintsTour implements Tour
{
    public function __construct(private ComplaintActivity $complaints) {}

    public function steps(): array
    {
        return [
            new TourStep(
                'complaints.receive', TourKey::Complaints, 'Llega una denuncia',
                'Muestra el QR del canal y pide a alguien del público que haga una denuncia de prueba.',
                'El canal es público y anónimo: quien denuncia no necesita cuenta ni dar su nombre.',
                'watch', 'tenant.complaints.channel.show', 'complaint-channel-qr',
            ),
            new TourStep(
                'complaints.acknowledge', TourKey::Complaints, 'Atiende la denuncia',
                'Abre la denuncia nueva y acusa de recibido.',
                'Quien denunció ve el avance con su clave, y RH deja constancia de cada paso.',
                'do', 'tenant.complaints.index', null,
            ),
        ];
    }

    public function states(DemoSession $session): array
    {
        $counts = $this->complaints->since($session->since());

        return [
            'complaints.receive' => StepState::counted($counts->received, '1 denuncia recibida', 'denuncias recibidas'),
            'complaints.acknowledge' => new StepState($counts->acknowledged >= 1),
        ];
    }
}
