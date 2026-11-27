<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Campaigns\Contracts\CampaignActivity;
use App\Modules\Campaigns\Data\CenterCampaignActivity;
use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;

/**
 * NOM-035: the evaluation of the session's center, from the draft to the results and the Guía I list. The center's activity
 * is read once and kept: the steps' states and the evaluation «Llévame» opens come from the same read.
 */
final class Nom035Tour implements Tour
{
    /** @var array<string, CenterCampaignActivity> by center */
    private array $activity = [];

    public function __construct(private readonly CampaignActivity $campaigns) {}

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
                'Las respuestas no guardan ninguna liga con la persona. Mientras tanto, RH solo ve cuántas van.',
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
                'El diagnóstico queda listo para actuar y para presentarlo ante la STPS. En la demo el mínimo por grupo es 1: con tan pocas personas, un resultado podría identificar respuestas.',
                'watch', 'tenant.campaigns.results', null,
                '¿Qué dicen los resultados de la evaluación de este centro?', visit: 'tenant.campaigns.results',
            ),
            new TourStep(
                'nom035.screenings', TourKey::Nom035, 'Canalización de la Guía I',
                'Abre la lista de canalización de la Guía I.',
                'La norma obliga a canalizar a quien vivió un acontecimiento traumático severo, y solo los roles autorizados lo ven.',
                'watch', 'tenant.campaigns.screenings', null, visit: 'tenant.campaigns.screenings',
            ),
        ];
    }

    public function states(DemoSession $session): array
    {
        if ($session->work_center_id === null) {
            return array_fill_keys(['nom035.draft', 'nom035.launch', 'nom035.answers', 'nom035.close'], StepState::pending());
        }

        $activity = $this->activity($session->work_center_id);

        return [
            'nom035.draft' => new StepState($activity->campaignId !== null),
            'nom035.launch' => new StepState($activity->launched),
            'nom035.answers' => Answers::state($activity->launched, $activity->invited, $activity->answered, $activity->closed),
            'nom035.close' => new StepState($activity->closed),
        ];
    }

    /** The evaluation of the session's center its pages open; null while there is none (or no center yet). */
    public function campaignId(DemoSession $session): ?string
    {
        return $session->work_center_id === null ? null : $this->activity($session->work_center_id)->campaignId;
    }

    private function activity(string $workCenterId): CenterCampaignActivity
    {
        return $this->activity[$workCenterId] ??= $this->campaigns->atCenter($workCenterId);
    }
}
