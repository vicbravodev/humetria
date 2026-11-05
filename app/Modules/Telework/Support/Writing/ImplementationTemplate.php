<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

/**
 * Cómo se empieza a teletrabajar (num. 5.6) with the four questions of docs/02 («Documentos con preguntas»): who asks, who
 * approves and how soon, what is signed and what equipment is handed over. Incisos a) to d) of 5.6 are base texts with the
 * key of their inciso.
 *
 * TODO-LEGAL: a lawyer validates every text here before production; the options and the wording are Humetría's.
 */
final readonly class ImplementationTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'humetria-2026-11-05';
    }

    public function name(): string
    {
        return 'Cómo se empieza a teletrabajar';
    }

    public function title(): string
    {
        return 'Cómo se empieza a teletrabajar';
    }

    public function summary(): string
    {
        return 'Los pasos para que alguien pase de la oficina a su casa: quién lo pide, quién aprueba, qué se firma.';
    }

    public function minutes(): int
    {
        return 5;
    }

    public function questions(): array
    {
        return [
            new Question('request', 'Quién lo solicita', '¿Quién puede pedir empezar a teletrabajar?', [
                new Field('requester', 'Quién', ['person' => 'La persona, a su jefe directo', 'company' => 'La empresa, cuando el puesto lo permite', 'both' => 'Las dos']),
            ]),
            new Question('approval', 'Quién aprueba', '¿Quién aprueba y en cuánto tiempo?', [
                new Field('approver', 'Quién aprueba', ['boss' => 'Su jefe directo', 'hr' => 'Recursos Humanos'], shown: true),
                new Field('approval_days', 'En cuánto tiempo', ['five' => 'En 5 días hábiles', 'ten' => 'En 10 días hábiles', 'fifteen' => 'En 15 días hábiles'], shown: true),
            ]),
            new Question('signing', 'Qué se firma', '¿Qué se firma antes de empezar?', [
                new Field('signed', 'Qué', ['annex' => 'Anexo al contrato de trabajo', 'checklist' => 'Lista de verificación del lugar de trabajo', 'equipment' => 'Responsiva del equipo entregado'], multiple: true, default: ['annex', 'checklist']),
            ], 'Elige todo lo que se firma.'),
            new Question('equipment', 'Qué equipo se entrega', '¿Qué equipo le entregas a quien empieza?', [
                new Field('handed', 'Equipo', ['computer' => 'Computadora', 'phone' => 'Teléfono', 'chair' => 'Silla ergonómica', 'headset' => 'Diadema', 'monitor' => 'Monitor'], multiple: true, columns: 2),
            ], 'Elige todo lo que entregas.'),
        ];
    }

    public function sections(Answers $answers): array
    {
        $requester = $answers->phrase('requester', [
            'person' => 'La persona pide a su jefe directo empezar a teletrabajar.',
            'company' => 'La empresa propone el teletrabajo cuando el puesto lo permite y la persona está de acuerdo.',
            'both' => 'Lo puede pedir la persona a su jefe directo o proponerlo la empresa cuando el puesto lo permite; en los dos casos, de común acuerdo.',
        ]);
        $approver = $answers->phrase('approver', ['boss' => 'su jefe directo', 'hr' => 'Recursos Humanos']);
        $days = $answers->phrase('approval_days', ['five' => '5', 'ten' => '10', 'fifteen' => '15']);
        $signed = $answers->list('signed', ['annex' => 'el anexo al contrato de trabajo', 'checklist' => 'la lista de verificación del lugar de trabajo', 'equipment' => 'la responsiva del equipo entregado']);
        $handed = $answers->list('handed', ['computer' => 'computadora', 'phone' => 'teléfono', 'chair' => 'silla ergonómica', 'headset' => 'diadema', 'monitor' => 'monitor']);

        return [
            new Section('request', 'Quién lo solicita', $requester),
            new Section('approval', 'Quién aprueba', $approver === null || $days === null ? null : "Aprueba {$approver} en un máximo de {$days} días hábiles, una vez que la Comisión de Seguridad e Higiene valida el lugar de trabajo."),
            new Section('signing', 'Qué se firma', $signed === null ? null : "Antes de empezar se firman {$signed}."),
            new Section('equipment', 'Qué equipo se entrega', $handed === null ? null : "Al empezar, la empresa le entrega {$handed}, con su listado firmado."),
            // TODO-LEGAL: base texts for incisos a) to d) of 5.6, Humetría's wording.
            new Section('a', 'Comunicación', 'La persona y el centro de trabajo se comunican por los medios que establece la Política de Teletrabajo.', base: true),
            new Section('b', 'Cuándo asiste al centro', 'La persona asiste al centro de trabajo cuando se le convoca con anticipación a reuniones, capacitaciones o revisiones de seguridad.', base: true),
            new Section('c', 'Supervisión', 'Su jefe directo supervisa el trabajo por los resultados acordados, sin mecanismos que invadan su privacidad.', base: true),
            new Section('d', 'Mantenimiento del equipo', 'El equipo se mantiene como dice el programa de mantenimiento del equipo de la empresa.', base: true),
        ];
    }
}
