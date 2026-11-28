<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Assistant\Contracts\HumiActivity;
use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;

/**
 * Humi: the presenter asks it how compliance stands. The detector asks whether anyone in the demo asked since the session
 * started, not the signed-in account: the room reads it as the superadmin, the dock as the demo's owner or its HR. The
 * demo's story writes no usage and each session starts on a reset demo: whatever is there was asked during the session.
 */
final readonly class HumiTour implements Tour
{
    public function __construct(private HumiActivity $humi) {}

    public function steps(): array
    {
        return [
            new TourStep(
                'humi.ask', TourKey::Humi, 'Pregúntale a Humi',
                'Abre a Humi y pregúntale cómo va el cumplimiento.',
                'Humi lee el estado de cada módulo y responde con conteos, nunca con respuestas ni personas.',
                'do', 'tenant.dashboard', 'humi-launcher',
                '¿Cómo va el cumplimiento de la empresa?',
            ),
        ];
    }

    public function states(DemoSession $session): array
    {
        return ['humi.ask' => new StepState($this->humi->askedSince($session->since()))];
    }
}
