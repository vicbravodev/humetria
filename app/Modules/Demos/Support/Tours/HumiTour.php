<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Assistant\Contracts\HumiActivity;
use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use Illuminate\Contracts\Auth\Factory;

/**
 * Humi: the presenter asks it how compliance stands. Humi keeps the usage under the account the panel is open as (the
 * demo's owner while impersonating), so the detector asks for the signed-in account. The demo's story writes no usage and
 * each session starts on a reset demo: whatever is there was asked during the session.
 */
final readonly class HumiTour implements Tour
{
    public function __construct(private HumiActivity $humi, private Factory $auth) {}

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
        $userId = $this->auth->guard()->id();

        return ['humi.ask' => new StepState(is_int($userId) && $this->humi->hasAsked($userId))];
    }
}
