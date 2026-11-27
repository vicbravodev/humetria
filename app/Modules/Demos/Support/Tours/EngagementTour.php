<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support\Tours;

use App\Modules\Demos\Data\StepState;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Engagement\Contracts\EngagementActivity;
use LogicException;

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
                'La invitación sale por correo y WhatsApp, y cada respuesta es anónima.',
                'do', 'tenant.climate.index', null,
            ),
            new TourStep(
                'engagement.answers', TourKey::Engagement, 'El público contesta',
                'Pide al público que conteste desde su celular.',
                'RH ve cuántos contestaron, nunca quién contestó qué.',
                'watch', 'tenant.climate.index', null,
            ),
            new TourStep(
                'engagement.results', TourKey::Engagement, 'Mira los resultados',
                'Abre los resultados de la encuesta.',
                'El resultado dice qué cuidar primero, sin exponer a nadie.',
                'watch', 'tenant.climate.index', null,
                '¿Qué dice la encuesta de clima?',
            ),
        ];
    }

    public function state(TourStep $step, DemoSession $session): StepState
    {
        $survey = $this->engagement->since($session->since());

        return match ($step->key) {
            'engagement.create' => new StepState($survey->created),
            'engagement.launch' => new StepState($survey->launched),
            'engagement.answers' => Answers::state($survey->launched, $survey->invited, $survey->answered, $survey->closed),
            'engagement.results' => new StepState(in_array('tenant.climate.results', $session->visited, true)),
            default => throw new LogicException("Unknown step {$step->key}."),
        };
    }
}
