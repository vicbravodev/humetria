<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support\Writing;

use App\Support\Writing\Answers;
use App\Support\Writing\Section;
use App\Support\Writing\WrittenTemplate;

/**
 * The possible effects on health of the exposure to the psychosocial risk factors (num. 5.7 f): no question, Humetría's draft
 * to review (board V4-Difusion: «Revísalo con un médico antes de publicar»). The definition is the numeral 4.7, literal; what
 * the people can do is Humetría's.
 *
 * TODO-LEGAL: a person with medical or legal criteria validates the second paragraph before production (criterio C-52).
 */
final readonly class HealthTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'questions-2026-11-05';
    }

    public function name(): string
    {
        return 'Alteraciones a la salud';
    }

    public function title(): string
    {
        return 'Posibles alteraciones a la salud';
    }

    public function summary(): string
    {
        return 'Hay un borrador de Humetría. Revísalo con un médico antes de publicar.';
    }

    public function minutes(): int
    {
        return 3;
    }

    public function questions(): array
    {
        return [];
    }

    public function sections(Answers $answers): array
    {
        return [
            new Section('body', 'Cuándo buscar ayuda médica', "Los factores de riesgo psicosocial son aquellos que pueden provocar trastornos de ansiedad, no orgánicos del ciclo sueño-vigilia y de estrés grave y de adaptación, derivado de la naturaleza de las funciones del puesto de trabajo, el tipo de jornada de trabajo y la exposición a acontecimientos traumáticos severos o a actos de violencia laboral al trabajador, por el trabajo desarrollado.\n\nSi usted nota alguno de estos síntomas, puede acudir a su institución de seguridad social o al médico del centro de trabajo, o hablar con Recursos Humanos.", base: true),
        ];
    }
}
