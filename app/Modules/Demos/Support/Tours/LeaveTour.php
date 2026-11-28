<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Leave\Contracts\LeaveActivity;
use App\Modules\WorkerAccess\Contracts\WorkerAccessActivity;

/**
 * Vacaciones, on the presenter's computer: the portal of a person of the audience, their key, a request, its approval and
 * the balance. Opening the portal and creating the key are one step: no contract tells a link was opened, a new key does.
 */
final readonly class LeaveTour implements Tour
{
    public function __construct(private LeaveActivity $leave, private WorkerAccessActivity $access) {}

    public function steps(): array
    {
        return [
            new TourStep(
                'leave.key', TourKey::Leave, 'Entra al portal y crea la llave',
                'Abre en tu computadora el portal de una persona del público y crea su llave con la huella.',
                'La persona entra a su portal sin contraseña y sin instalar nada.',
                'do', 'tenant.worker-access.index', null,
            ),
            new TourStep(
                'leave.request', TourKey::Leave, 'Pide días',
                'Desde el portal, pide unos días de vacaciones.',
                'La solicitud se confirma con la llave de la persona, como una firma.',
                'do', 'tenant.vacations.requests.index', null,
            ),
            new TourStep(
                'leave.approve', TourKey::Leave, 'Aprueba como RH',
                'Vuelve al panel y aprueba la solicitud.',
                'La persona recibe el aviso por correo y su saldo lo refleja.',
                'do', 'tenant.vacations.requests.index', 'approve-leave-request',
            ),
            new TourStep(
                'leave.balance', TourKey::Leave, 'Mira el saldo',
                'Abre los saldos de vacaciones.',
                'Cada saldo sigue lo que marca la ley, sin hojas de cálculo.',
                'watch', 'tenant.vacations.balances.index', null, visit: 'tenant.vacations.balances.index',
            ),
        ];
    }

    public function states(DemoSession $session): array
    {
        $leave = $this->leave->since($session->since());

        return [
            'leave.key' => new StepState($this->access->since($session->since())->keys >= 1),
            'leave.request' => new StepState($leave->requested >= 1),
            'leave.approve' => new StepState($leave->approved >= 1),
        ];
    }
}
