<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support\Writing;

use App\Support\Writing\Answers;
use App\Support\Writing\Field;
use App\Support\Writing\Question;
use App\Support\Writing\Section;
use App\Support\Writing\WrittenTemplate;

/**
 * The measures of prevention and control of the psychosocial risk factors (num. 5.7 c) written with three questions: how
 * often the company trains, how often it reviews the workloads and what else it does (the board V4-Difusion names
 * «capacitación, cargas de trabajo, pausas»). What the Programa de intervención shows is added to it on the page, never in its
 * place (criterio C-52).
 *
 * TODO-LEGAL: the questions, options and wording are Humetría's (the board draws only the block of violence); a lawyer
 * validates them before production.
 */
final readonly class PreventionTemplate implements WrittenTemplate
{
    private const array ACTIONS = ['breaks' => 'Pausas acordadas por turno', 'schedules' => 'Horarios y descansos que se respetan', 'support' => 'Apoyo psicológico para quien lo pida', 'listening' => 'Reuniones para escuchar al equipo', 'campaigns' => 'Campañas de bienestar'];

    public function version(): string
    {
        return 'questions-2026-11-05';
    }

    public function name(): string
    {
        return 'Prevención y control';
    }

    public function title(): string
    {
        return 'Medidas de prevención y control';
    }

    public function summary(): string
    {
        return 'Lo que hace la empresa para prevenir: capacitación, cargas de trabajo, pausas.';
    }

    public function minutes(): int
    {
        return 5;
    }

    public function questions(): array
    {
        return [
            new Question('training', 'Capacitación', '¿Cada cuánto capacitan sobre los riesgos psicosociales?', [
                new Field('training', 'Cada cuánto', ['yearly' => 'Una vez al año', 'semester' => 'Cada seis meses', 'induction' => 'Solo en la inducción de quien entra']),
            ]),
            new Question('workload', 'Cargas de trabajo', '¿Cada cuánto revisan las cargas de trabajo?', [
                new Field('workload', 'Cada cuánto', ['quarter' => 'Cada trimestre', 'semester' => 'Cada seis meses', 'yearly' => 'Una vez al año']),
            ], 'Una carga excesiva es uno de los factores de riesgo que mide la evaluación.'),
            new Question('actions', 'Otras medidas', '¿Qué más hacen para prevenir?', [
                new Field('actions', 'Qué más', self::ACTIONS, multiple: true),
            ], 'Elige solo lo que de verdad hacen.'),
        ];
    }

    public function sections(Answers $answers): array
    {
        $training = $answers->phrase('training', ['yearly' => 'una vez al año', 'semester' => 'cada seis meses', 'induction' => 'en la inducción de quien entra']);
        $workload = $answers->phrase('workload', ['quarter' => 'cada trimestre', 'semester' => 'cada seis meses', 'yearly' => 'una vez al año']);
        $actions = $answers->list('actions', array_map(fn (string $label): string => mb_strtolower(mb_substr($label, 0, 1)).mb_substr($label, 1), self::ACTIONS));

        return [
            new Section('training', 'Capacitación', $training === null ? null : "Capacitamos a todo el personal sobre los riesgos psicosociales {$training}."),
            new Section('workload', 'Cargas de trabajo', $workload === null ? null : "Revisamos las cargas de trabajo {$workload}."),
            new Section('actions', 'Otras medidas', $actions === null ? null : "También hay {$actions}."),
        ];
    }
}
