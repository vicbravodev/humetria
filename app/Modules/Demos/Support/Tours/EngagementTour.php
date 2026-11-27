<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Engagement\Contracts\EngagementActivity;

/**
 * Clima y pulso, with a climate survey: the demo's story leaves a pulse open and a company has one open survey of each
 * kind, so a new pulse could not be launched. The steps take the panel to the list: the survey's own pages need its id,
 * which the contract does not give.
 */
final readonly class EngagementTour implements Tour
{
    public function __construct(private EngagementActivity $engagement) {}

    public function steps(): array
    {
        return [
            new TourStep(
                'engagement.create', TourKey::Engagement, 'Crea la encuesta de clima',
                'Crea una encuesta de clima para el centro de la sesión.',
                'El banco de preguntas ya está probado: solo escoges a quién preguntar.',
                'do', 'tenant.climate.index', 'new-climate-survey',
            ),
            new TourStep(
                'engagement.launch', TourKey::Engagement, 'Lanza la encuesta',
                'Abre la encuesta y lánzala.',
                'La invitación sale por correo y WhatsApp, y cada respuesta se guarda sin el nombre de quien contesta.',
                'do', 'tenant.climate.index', null,
            ),
            new TourStep(
                'engagement.answers', TourKey::Engagement, 'El público contesta',
                'Pide al público que conteste desde su celular.',
                'Las respuestas no guardan ninguna liga con la persona. Mientras tanto, RH solo ve cuántas van.',
                'watch', 'tenant.climate.index', null,
            ),
            new TourStep(
                'engagement.results', TourKey::Engagement, 'Mira los resultados',
                'Abre los resultados de la encuesta.',
                'El resultado dice qué cuidar primero. En la demo el mínimo por grupo es 1: con tan pocas personas, un resultado podría identificar respuestas.',
                'watch', 'tenant.climate.index', null,
                '¿Qué dice la encuesta de clima?', visit: 'tenant.climate.results',
            ),
        ];
    }

    public function states(DemoSession $session): array
    {
        $survey = $this->engagement->since($session->since());

        return [
            'engagement.create' => new StepState($survey->created),
            'engagement.launch' => new StepState($survey->launched),
            'engagement.answers' => Answers::state($survey->launched, $survey->invited, $survey->answered, $survey->closed),
        ];
    }
}
