<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

use App\Support\Writing\Answers;
use App\Support\Writing\Field;
use App\Support\Writing\Question;
use App\Support\Writing\Section;
use App\Support\Writing\WrittenTemplate;

/**
 * Regreso a la oficina (num. 5.10, LFT 330-G) with the four questions of docs/02 («Documentos con preguntas»): who may ask,
 * how far ahead, how it is agreed and written down, and what happens with the equipment.
 *
 * TODO-LEGAL: a lawyer validates every text here before production; the options and the wording are Humetría's (the
 * immediate change for family violence follows the example L of the DOF's Apéndice 1).
 */
final readonly class ReversibilityTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'humetria-2026-11-05';
    }

    public function name(): string
    {
        return 'Regreso a la oficina';
    }

    public function title(): string
    {
        return 'Regreso al trabajo presencial';
    }

    public function summary(): string
    {
        return 'Cómo alguien puede volver a trabajar en el centro, por decisión suya o de la empresa.';
    }

    public function minutes(): int
    {
        return 4;
    }

    public function questions(): array
    {
        return [
            new Question('request', 'Quién lo pide', '¿Quién puede pedir el regreso a la oficina?', [
                new Field('requester', 'Quién', ['person' => 'La persona', 'company' => 'La empresa', 'both' => 'Las dos']),
            ]),
            new Question('notice', 'Anticipación', '¿Con cuánta anticipación se pide?', [
                new Field('days', 'Anticipación', ['fifteen' => 'Con 15 días', 'twenty' => 'Con 20 días', 'thirty' => 'Con 30 días']),
            ]),
            new Question('record', 'Cómo se acuerda', '¿Cómo se acuerda y se deja por escrito?', [
                new Field('form', 'Cómo', ['letter' => 'Por escrito, con la firma de las dos partes', 'addendum' => 'Con una adenda al contrato de trabajo']),
            ]),
            new Question('equipment', 'El equipo', '¿Qué pasa con el equipo que se entregó?', [
                new Field('equipment', 'El equipo', ['back' => 'Se devuelve a la empresa', 'stays' => 'Se lo queda para usarlo en el centro']),
            ]),
        ];
    }

    public function sections(Answers $answers): array
    {
        $requester = $answers->phrase('requester', [
            'person' => 'La persona puede pedir regresar al trabajo presencial, por ejemplo si cambian las condiciones de seguridad y salud de su lugar de trabajo.',
            'company' => 'La empresa puede proponer el regreso al trabajo presencial cuando lo justifiquen las necesidades del trabajo.',
            'both' => 'Lo pueden pedir la persona o la empresa, por ejemplo si cambian las condiciones de seguridad y salud del lugar de trabajo.',
        ]);
        $days = $answers->phrase('days', ['fifteen' => '15', 'twenty' => '20', 'thirty' => '30']);
        $form = $answers->phrase('form', [
            'letter' => 'El regreso se acuerda entre la persona y la empresa y se deja por escrito, con la firma de las dos partes.',
            'addendum' => 'El regreso se acuerda entre la persona y la empresa y se firma como adenda al contrato de trabajo.',
        ]);
        $equipment = $answers->phrase('equipment', [
            'back' => 'La persona devuelve el equipo que se le entregó para teletrabajar, con su listado firmado.',
            'stays' => 'La persona conserva el equipo que se le entregó y lo usa en el centro de trabajo.',
        ]);

        return [
            new Section('request', 'Quién lo pide', $requester),
            new Section('notice', 'Con cuánta anticipación', $days === null ? null : "Se pide con {$days} días de anticipación, salvo un caso de violencia familiar, que se atiende de inmediato."),
            new Section('record', 'Cómo se acuerda', $form),
            new Section('equipment', 'El equipo', $equipment),
        ];
    }
}
