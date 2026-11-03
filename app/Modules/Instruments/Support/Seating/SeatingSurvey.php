<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Seating;

use App\Modules\Instruments\Data\GateDefinition;
use App\Modules\Instruments\Data\InstrumentDefinition;
use App\Modules\Instruments\Data\ItemDefinition;
use App\Modules\Instruments\Data\SectionDefinition;

/**
 * Humetría's confidential survey about the seats and rests of a work center (criterio C-19). It is not text of the DOF and
 * adds nothing to anyone's risk level: its questions carry their own codes (SS-nn) and say which obligation each one
 * detects. Whoever does not work standing answers the first question «no» and is asked nothing else.
 */
final class SeatingSurvey
{
    public const string KEY = 'seating.survey';

    public const string NOTICE = 'Las preguntas SS-nn son de Humetría: no son texto del DOF y no suman al nivel de riesgo de ninguna persona.';

    /** @var array<int, array{string, string}> number => [question, the obligation it detects] */
    private const array ITEMS = [
        1 => ['¿Hay un asiento o silla con respaldo disponible en su puesto de trabajo, o cerca de él?', 'Asientos a disposición de todas las personas trabajadoras (LFT 132 V)'],
        2 => ['¿Puede sentarse cuando lo necesita, sin que le llamen la atención ni se vea afectado en su trabajo?', 'No se puede impedir sentarse (LFT 133 XVII Bis)'],
        3 => ['¿Alguien le ha impedido o le ha prohibido sentarse durante su jornada?', 'Prohibición tácita de sentarse (LFT 133 XVII Bis)'],
        4 => ['¿Conoce las pausas que su empresa acordó para quienes trabajan de pie?', 'Pausas acordadas e informadas (Disposiciones, Quinta G V)'],
        5 => ['¿Esas pausas se respetan en la práctica?', 'Pausas que se cumplen (Disposiciones, Quinta G V)'],
        6 => ['¿El asiento que usted usa, o podría usar, está en buen estado?', 'Asiento adecuado y en condiciones (Disposiciones, Quinta F)'],
        7 => ['¿En un día normal está de pie más de tres horas seguidas?', 'Bipedestación prolongada (Disposiciones, Cuarta)'],
        8 => ['¿Siente molestias en las piernas o en la espalda por trabajar de pie?', 'Signos o síntomas relacionados con la postura (Disposiciones, Quinta J)'],
    ];

    public static function definition(): InstrumentDefinition
    {
        $items = array_map(fn (int $number, array $item): ItemDefinition => new ItemDefinition($number, $item[0]), array_keys(self::ITEMS), array_values(self::ITEMS));

        return new InstrumentDefinition(
            self::KEY,
            Assessment::VERSION,
            'Asientos y descansos en su centro de trabajo',
            'Encuesta de Ley Silla',
            false,
            [['value' => 'yes', 'label' => 'Sí'], ['value' => 'no', 'label' => 'No'], ['value' => 'not_applicable', 'label' => 'No aplica']],
            [new SectionDefinition(
                null,
                'Estas preguntas son sobre los asientos y las pausas de su centro de trabajo. Sus respuestas son anónimas: nadie de su empresa puede saber quién contestó qué.',
                $items,
                new GateDefinition('stands', '¿Trabaja de pie en su centro de trabajo?', 'Conteste «Sí» si pasa parte de su jornada de pie. Si no, la encuesta termina aquí.'),
            )],
            3,
        );
    }

    /** The code of a question: SS-01, SS-02… */
    public static function code(int $number): string
    {
        return sprintf('SS-%02d', $number);
    }

    /**
     * The obligation each question detects, by code.
     *
     * @return array<string, string>
     */
    public static function requirements(): array
    {
        $requirements = [];

        foreach (self::ITEMS as $number => $item) {
            $requirements[self::code($number)] = $item[1];
        }

        return $requirements;
    }
}
