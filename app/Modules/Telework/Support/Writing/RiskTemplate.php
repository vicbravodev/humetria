<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

/**
 * Riesgos de trabajar desde casa (num. 5.3) with four questions (board V3-Doc-Riesgos; docs/02, «Documentos con
 * preguntas»). The question of the risks, its options, what it marks and the text of the first three risks are the board's.
 *
 * TODO-LEGAL: a lawyer validates every text here before production; the other questions, options and paragraphs are
 * Humetría's.
 */
final readonly class RiskTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'humetria-2026-11-05';
    }

    public function name(): string
    {
        return 'Riesgos de trabajar desde casa';
    }

    public function title(): string
    {
        return 'Riesgos de trabajar desde casa y cómo prevenirlos';
    }

    public function summary(): string
    {
        return 'Qué riesgos tiene el teletrabajo y cómo evitarlos. Se entrega a cada persona.';
    }

    public function minutes(): int
    {
        return 5;
    }

    public function questions(): array
    {
        return [
            new Question('audience', 'Tipo de trabajo', '¿Qué tipo de trabajo hacen desde casa?', [
                new Field('work', 'Tipo de trabajo', ['computer' => 'Trabajo de oficina con computadora y teléfono', 'phone' => 'Atención por teléfono']),
            ]),
            new Question('risks', 'Riesgos', '¿Qué riesgos quieres explicarle a tu equipo?', [
                new Field('risks', 'Riesgos', [
                    'posture' => 'Postura y muchas horas frente a la pantalla',
                    'electric' => 'Instalación eléctrica y extensiones sobrecargadas',
                    'isolation' => 'Aislamiento y falta de contacto con el equipo',
                    'hours' => 'Jornadas sin horario de fin',
                    'cables' => 'Caídas o tropiezos con cables',
                ], multiple: true, default: ['posture', 'electric', 'isolation']),
            ], 'Marcamos los más comunes en trabajo de oficina desde casa. Quita o agrega según lo que hacen.'),
            new Question('notify', 'A quién avisar', '¿A quién avisa alguien si nota un riesgo o tiene un accidente?', [
                new Field('contacts', 'A quién', ['boss' => 'Su jefe directo', 'hr' => 'Recursos Humanos', 'committee' => 'La Comisión de Seguridad e Higiene'], multiple: true),
            ], 'Elige todos los que aplican.'),
            new Question('delivery', 'Cómo se entrega', '¿Cómo le entregas este documento a cada persona?', [
                new Field('delivery', 'Cómo', ['portal' => 'En su portal, con acuse de cada persona', 'induction' => 'En la inducción, con firma en papel']),
            ]),
        ];
    }

    public function sections(Answers $answers): array
    {
        $work = $answers->phrase('work', ['computer' => 'con computadora y teléfono', 'phone' => 'atendiendo por teléfono']);
        $risks = array_values(array_intersect_key([
            'posture' => 'Postura y pantalla. Use una silla con respaldo, la pantalla a la altura de los ojos y haga una pausa breve cada hora.',
            'electric' => 'Instalación eléctrica. No conecte varios aparatos a una sola extensión; avise si un contacto está dañado.',
            'isolation' => 'Aislamiento. Participe en las reuniones del equipo y avise si se siente desconectado.',
            // TODO-LEGAL: the board gives no text for these two.
            'hours' => 'Jornadas sin fin. Respete su horario y sus pausas, y desconéctese al terminar.',
            'cables' => 'Cables. Mantenga los cables fuera del paso y bien sujetos para no tropezar.',
        ], array_flip($answers->many('risks'))));
        $contacts = $answers->list('contacts', ['boss' => 'su jefe directo', 'hr' => 'Recursos Humanos', 'committee' => 'la Comisión de Seguridad e Higiene'], 'o');
        $delivery = $answers->phrase('delivery', [
            'portal' => 'Cada persona recibe este documento en su portal y confirma ahí que lo leyó.',
            'induction' => 'Este documento se entrega en la inducción y cada persona firma que lo recibió.',
        ]);

        return [
            new Section('audience', 'Para quién es', $work === null ? null : "Para quienes trabajan desde casa {$work} más del 40 % de su tiempo."),
            new Section('risks', 'Los riesgos y qué hacer', $risks === [] ? null : implode("\n\n", $risks)),
            new Section('notify', 'A quién avisar', $contacts === null ? null : "Si nota un riesgo en su lugar de trabajo o tiene un accidente, avise a {$contacts}. También puede avisar desde su portal."),
            new Section('delivery', 'Cómo se entrega', $delivery),
        ];
    }
}
