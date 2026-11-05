<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

/**
 * Participación en las comisiones (num. 5.14) with the three questions of docs/02 («Documentos con preguntas»): how someone
 * in telework gets to a commission, how they take part from afar and in what time.
 *
 * TODO-LEGAL: a lawyer validates every text here before production; the options and the wording are Humetría's.
 */
final readonly class CommitteeTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'humetria-2026-11-05';
    }

    public function name(): string
    {
        return 'Participación en las comisiones';
    }

    public function title(): string
    {
        return 'Participación en las comisiones';
    }

    public function summary(): string
    {
        return 'Cómo participa quien teletrabaja en la Comisión de Seguridad e Higiene y en la de capacitación.';
    }

    public function minutes(): int
    {
        return 3;
    }

    public function questions(): array
    {
        return [
            new Question('selection', 'Cómo se elige', '¿Cómo llega alguien en teletrabajo a una comisión?', [
                new Field('selection', 'Cómo', ['vote' => 'Por votación, como el resto del personal', 'invite' => 'Por invitación de la empresa']),
            ]),
            new Question('remote', 'Cómo participa', '¿Cómo participa a distancia?', [
                new Field('modes', 'Cómo', ['video' => 'Por videollamada en las reuniones', 'visits' => 'En persona, con los traslados a cargo de la empresa', 'documents' => 'Revisando documentos en línea'], multiple: true, default: ['video']),
            ], 'Elige todas las formas que ofreces.'),
            new Question('time', 'Tiempo', '¿En qué tiempo participa?', [
                new Field('time', 'Tiempo', ['within' => 'Dentro de su jornada, sin descuento', 'agreed' => 'En un horario que acuerda con su jefe']),
            ]),
        ];
    }

    public function sections(Answers $answers): array
    {
        $selection = $answers->phrase('selection', [
            'vote' => 'Las personas en teletrabajo pueden formar parte de la Comisión de Seguridad e Higiene y de la Comisión mixta de capacitación, adiestramiento y productividad, elegidas por votación como el resto del personal.',
            'invite' => 'La empresa invita a las personas en teletrabajo a formar parte de la Comisión de Seguridad e Higiene y de la Comisión mixta de capacitación, adiestramiento y productividad.',
        ]);
        $modes = $answers->list('modes', ['video' => 'por videollamada en las reuniones', 'visits' => 'en persona, con los traslados a cargo de la empresa', 'documents' => 'revisando los documentos en línea']);
        $time = $answers->phrase('time', [
            'within' => 'El tiempo en la comisión cuenta como parte de su jornada y no se descuenta de su sueldo.',
            'agreed' => 'El tiempo en la comisión se acuerda con su jefe directo dentro de la jornada, sin descuento de su sueldo.',
        ]);

        return [
            new Section('selection', 'Cómo se elige', $selection),
            new Section('remote', 'Cómo participa', $modes === null ? null : "Participa {$modes}, con las herramientas que le da la empresa."),
            new Section('time', 'Tiempo para participar', $time),
        ];
    }
}
