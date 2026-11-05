<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

use App\Support\Writing\Answers;
use App\Support\Writing\Field;
use App\Support\Writing\Question;
use App\Support\Writing\Section;
use App\Support\Writing\WrittenTemplate;

/**
 * Mantenimiento del equipo (num. 5.8) with the four questions of docs/02 («Documentos con preguntas»): what equipment, how
 * often, who does it and how a fault is reported.
 *
 * TODO-LEGAL: a lawyer validates every text here before production; the options and the wording are Humetría's.
 */
final readonly class MaintenanceTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'humetria-2026-11-05';
    }

    public function name(): string
    {
        return 'Mantenimiento del equipo';
    }

    public function title(): string
    {
        return 'Programa de mantenimiento del equipo';
    }

    public function summary(): string
    {
        return 'Cada cuánto y quién revisa la computadora y el equipo que entregas.';
    }

    public function minutes(): int
    {
        return 4;
    }

    public function questions(): array
    {
        return [
            new Question('equipment', 'Qué equipo', '¿Qué equipo entrega la empresa?', [
                new Field('items', 'Equipo', ['computer' => 'Computadora', 'phone' => 'Teléfono', 'monitor' => 'Monitor', 'headset' => 'Diadema', 'modem' => 'Módem o equipo de internet'], multiple: true, columns: 2),
            ], 'Elige todo lo que entregas.'),
            new Question('frequency', 'Cada cuánto', '¿Cada cuánto se revisa el equipo?', [
                new Field('every', 'Cada cuánto', ['quarter' => 'Cada tres meses', 'semester' => 'Cada seis meses', 'year' => 'Una vez al año']),
            ]),
            new Question('provider', 'Quién lo hace', '¿Quién da el mantenimiento?', [
                new Field('provider', 'Quién', ['it' => 'El área de sistemas de la empresa', 'vendor' => 'Un proveedor externo']),
            ]),
            new Question('fault', 'Si algo falla', '¿Cómo se reporta una falla?', [
                new Field('report', 'Cómo', ['email' => 'Por correo al área de sistemas', 'phone' => 'Por teléfono al área de sistemas', 'boss' => 'Con su jefe directo']),
            ]),
        ];
    }

    public function sections(Answers $answers): array
    {
        $items = $answers->list('items', ['computer' => 'la computadora', 'phone' => 'el teléfono', 'monitor' => 'el monitor', 'headset' => 'la diadema', 'modem' => 'el módem o equipo de internet']);
        $every = $answers->phrase('every', ['quarter' => 'cada tres meses', 'semester' => 'cada seis meses', 'year' => 'una vez al año']);
        $provider = $answers->phrase('provider', ['it' => 'El área de sistemas de la empresa', 'vendor' => 'Un proveedor que contrata la empresa']);
        $report = $answers->phrase('report', ['email' => 'por correo al área de sistemas', 'phone' => 'por teléfono al área de sistemas', 'boss' => 'con su jefe directo']);

        return [
            new Section('equipment', 'Equipo que cubre', $items === null ? null : "Este programa cubre {$items} que la empresa entrega a las personas en teletrabajo."),
            new Section('frequency', 'Cada cuánto', $every === null ? null : "El equipo se revisa {$every}: se limpia, se actualiza y se comprueba que funcione de forma segura."),
            new Section('provider', 'Quién lo hace', $provider === null ? null : "{$provider} da el mantenimiento, a distancia o en el centro de trabajo, sin costo para la persona."),
            new Section('fault', 'Si algo falla', $report === null ? null : "Si el equipo falla, la persona lo reporta {$report} y la empresa lo repara o lo cambia."),
        ];
    }
}
