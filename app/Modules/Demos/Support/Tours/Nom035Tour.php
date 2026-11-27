<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Campaigns\Contracts\CampaignActivity;
use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use LogicException;

/** NOM-035: the evaluation of the session's center, from the draft to the results and the Guía I list. */
final readonly class Nom035Tour implements Tour
{
    public function __construct(private CampaignActivity $campaigns) {}

    public function steps(): array
    {
        return [
            new TourStep(
                'nom035.draft', TourKey::Nom035, 'Crea la evaluación',
                'Crea la evaluación NOM-035 del centro de la sesión.',
                'La plataforma escoge las guías que aplican y arma la lista de quién contesta.',
                'do', 'tenant.campaigns.index', 'new-evaluation',
            ),
            new TourStep(
                'nom035.launch', TourKey::Nom035, 'Lanza la evaluación',
                'Revisa la evaluación y lánzala.',
                'Cada persona recibe su invitación por correo y WhatsApp, con un enlace que solo sirve una vez.',
                'do', 'tenant.campaigns.show', 'launch-evaluation',
            ),
            new TourStep(
                'nom035.answers', TourKey::Nom035, 'El público contesta',
                'Pide al público que conteste desde su celular.',
                'Las respuestas son anónimas: RH ve el avance, nunca quién contestó qué.',
                'watch', 'tenant.campaigns.show', 'evaluation-progress',
            ),
            new TourStep(
                'nom035.close', TourKey::Nom035, 'Cierra la evaluación',
                'Cierra la evaluación para calcular los resultados.',
                'Al cerrar, nadie más puede contestar y los resultados quedan fijos para el informe.',
                'do', 'tenant.campaigns.show', 'close-evaluation',
            ),
            new TourStep(
                'nom035.results', TourKey::Nom035, 'Mira los resultados',
                'Abre los resultados y el informe de la evaluación.',
                'El diagnóstico queda listo para actuar y para presentarlo ante la STPS.',
                'watch', 'tenant.campaigns.results', null,
                '¿Qué dicen los resultados de la evaluación de este centro?',
            ),
            new TourStep(
                'nom035.screenings', TourKey::Nom035, 'Canalización de la Guía I',
                'Abre la lista de canalización de la Guía I.',
                'La norma obliga a canalizar a quien vivió un acontecimiento traumático severo, y solo los roles autorizados lo ven.',
                'watch', 'tenant.campaigns.screenings', null,
            ),
        ];
    }

    public function state(TourStep $step, DemoSession $session): StepState
    {
        if ($session->work_center_id === null) {
            return StepState::pending();
        }

        $activity = $this->campaigns->atCenter($session->work_center_id);

        return match ($step->key) {
            'nom035.draft' => new StepState($activity->campaignId !== null),
            'nom035.launch' => new StepState($activity->launched),
            'nom035.answers' => Answers::state($activity->launched, $activity->invited, $activity->answered, $activity->closed),
            'nom035.close' => new StepState($activity->closed),
            'nom035.results' => new StepState(in_array('tenant.campaigns.results', $session->visited, true)),
            'nom035.screenings' => new StepState(in_array('tenant.campaigns.screenings', $session->visited, true)),
            default => throw new LogicException("Unknown step {$step->key}."),
        };
    }
}
