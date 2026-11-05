<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support\Writing;

/**
 * Apoyo si alguien vive violencia en casa (num. 5.13) with the four questions of docs/02 («Documentos con preguntas»): who
 * to turn to inside the company, where it is referred outside, which temporary adjustments the company offers (5.13 asks
 * to consider going back to the center, for a time or for good) and who knows about a case.
 *
 * TODO-LEGAL: a lawyer validates every text here before production; the options and the wording are Humetría's (the
 * attention «expedita, discreta, con perspectiva de género y sin revictimizar» follows the example L of the DOF's
 * Apéndice 1). The public institutions are named generically, without phone numbers to keep up to date.
 */
final readonly class FamilyViolenceTemplate implements WrittenTemplate
{
    public function version(): string
    {
        return 'humetria-2026-11-05';
    }

    public function name(): string
    {
        return 'Apoyo si alguien vive violencia en casa';
    }

    public function title(): string
    {
        return 'Atención de casos de violencia familiar';
    }

    public function summary(): string
    {
        return 'Qué hace la empresa y a dónde canaliza si una persona en teletrabajo vive violencia familiar.';
    }

    public function minutes(): int
    {
        return 7;
    }

    public function questions(): array
    {
        return [
            new Question('contact', 'A quién acudir', '¿A quién puede acudir dentro de la empresa?', [
                new Field('contact', 'A quién', ['hr' => 'A Recursos Humanos', 'designated' => 'A una persona designada para estos casos', 'boss' => 'A su jefe directo']),
            ]),
            new Question('outside', 'Fuera de la empresa', '¿A dónde se canaliza fuera de la empresa?', [
                new Field('places', 'A dónde', ['emergency' => 'Emergencias 911', 'women' => 'El Instituto de las Mujeres de su estado', 'dif' => 'El DIF', 'prosecutor' => 'La Fiscalía de su estado'], multiple: true, default: ['emergency']),
            ], 'Elige las instituciones públicas que quieres nombrar.'),
            new Question('adjustments', 'Ajustes temporales', '¿Qué ajustes puede ofrecer la empresa?', [
                new Field('adjustments', 'Ajustes', [
                    'office' => 'Regresar a la oficina, por un tiempo o de forma permanente',
                    'place' => 'Cambiar de lugar de trabajo de inmediato',
                    'schedule' => 'Cambiar su horario',
                    'leave' => 'Un permiso con goce de sueldo',
                ], multiple: true, default: ['office']),
            ], 'La norma pide considerar el regreso al trabajo presencial, por un tiempo o de forma permanente.'),
            new Question('privacy', 'Confidencialidad', '¿Quién se entera de un caso?', [
                new Field('privacy', 'Quién', ['handler' => 'Solo quien lo atiende', 'handler_hr' => 'Quien lo atiende y Recursos Humanos']),
            ]),
        ];
    }

    public function sections(Answers $answers): array
    {
        $contact = $answers->phrase('contact', ['hr' => 'a Recursos Humanos', 'designated' => 'a la persona que la empresa designó para estos casos', 'boss' => 'a su jefe directo']);
        $places = $answers->list('places', ['emergency' => 'Emergencias 911', 'women' => 'el Instituto de las Mujeres de su estado', 'dif' => 'el DIF', 'prosecutor' => 'la Fiscalía de su estado'], 'o');
        $adjustments = $answers->list('adjustments', [
            'office' => 'regresar a trabajar en el centro, por un tiempo o de forma permanente',
            'place' => 'cambiar su lugar de trabajo de inmediato',
            'schedule' => 'cambiar su horario',
            'leave' => 'tomar un permiso con goce de sueldo',
        ], 'o');
        $privacy = $answers->phrase('privacy', [
            'handler' => 'Solo quien atiende el caso lo conoce. No se comparte con nadie más sin el permiso de la persona.',
            'handler_hr' => 'Solo quien atiende el caso y Recursos Humanos lo conocen. No se comparte con nadie más sin el permiso de la persona.',
        ]);

        return [
            new Section('contact', 'A quién acudir', $contact === null ? null : "Si una persona en teletrabajo vive violencia en su casa, puede acudir {$contact}, que la atiende de forma expedita, discreta y con perspectiva de género, sin revictimizarla."),
            new Section('outside', 'A dónde se canaliza', $places === null ? null : "La empresa le orienta y le acompaña para acudir a {$places}."),
            new Section('adjustments', 'Ajustes que ofrece la empresa', $adjustments === null ? null : "Mientras lo necesite, la persona puede {$adjustments}."),
            new Section('privacy', 'Confidencialidad', $privacy),
        ];
    }
}
