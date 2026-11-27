<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Organization\Contracts\Headcount;
use LogicException;

/** «El centro y su gente»: always first; the session's center and the people of the audience, which the others need. */
final readonly class BaseTour implements Tour
{
    public function __construct(private Headcount $headcount) {}

    public function steps(): array
    {
        return [
            new TourStep(
                'base.center', TourKey::Base, 'El centro de la sesión',
                'Abre los centros de trabajo y ubica el de esta sesión.',
                'Todo en la plataforma parte del centro de trabajo: ahí se aplican la NOM-035, la asistencia y lo demás.',
                'do', 'tenant.work-centers.index', 'work-centers-list',
            ),
            new TourStep(
                'base.people', TourKey::Base, 'Da de alta al público',
                'Agrega a cada persona del público en el centro de la sesión, con su correo, su teléfono y una fecha de ingreso de hace dos años.',
                'Con su gente cargada, la empresa invita y recolecta sin pedirle nada más a nadie; con su antigüedad, sus vacaciones ya salen con el saldo que marca la ley.',
                'do', 'tenant.employees.index', 'add-employee',
            ),
        ];
    }

    public function state(TourStep $step, DemoSession $session): StepState
    {
        return match ($step->key) {
            'base.center' => new StepState(in_array('tenant.work-centers.index', $session->visited, true)),
            'base.people' => $this->people($session->work_center_id),
            default => throw new LogicException("Unknown step {$step->key}."),
        };
    }

    private function people(?string $workCenterId): StepState
    {
        $people = $workCenterId === null ? 0 : ($this->headcount->byWorkCenter()[$workCenterId] ?? 0);

        return StepState::counted($people, '1 persona en el centro', 'personas en el centro');
    }
}
