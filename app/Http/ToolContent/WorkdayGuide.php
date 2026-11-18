<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The guide /guias/registro-de-jornada-2027: what changes with the reform, for whoever searches for it before they search
 * for software. Everything it says comes from docs/normas/jornada/criterios.md, with the article it rests on.
 *
 * @phpstan-import-type Guide from ToolPages
 */
final class WorkdayGuide
{
    /** @return Guide */
    public static function page(): array
    {
        return [
            'title' => 'Registro de jornada 2027: qué cambia en la LFT',
            'description' => 'Qué pide la reforma de 2026 a la Ley Federal del Trabajo: registro electrónico desde 2027, jornada de 46 a 40 horas, horas extra y multas.',
            'h1' => 'Qué cambia con el registro de jornada en 2027',
            'lead' => 'La reforma a la Ley Federal del Trabajo publicada en el DOF el 1 de mayo de 2026 reduce la jornada semanal poco a poco y obliga a registrar de forma electrónica la jornada de cada persona. Esto es lo que dice y lo que conviene preparar.',
            'updated' => '2026-11-17',
            'sections' => [
                [
                    'heading' => 'El registro electrónico entra en vigor el 1 de enero de 2027',
                    'paragraphs' => [
                        'La reforma agrega al artículo 132 la obligación de registrar de manera electrónica la jornada laboral de cada persona trabajadora, incluyendo su hora de inicio y de fin, y de entregarlo a la autoridad cuando lo pida.',
                        'La obligación entra en vigor el 1 de enero de 2027. La STPS expedirá disposiciones generales sobre su ámbito de aplicación y sus excepciones, y definirá a qué empresas aplica y si será gradual; mientras no lo haga, la ley no excluye al personal de confianza, a quien trabaja a distancia ni a las empresas pequeñas.',
                    ],
                    'sources' => ['LFT, art. 132, fracción XXXIV', 'Transitorio quinto del decreto del DOF del 1 de mayo de 2026'],
                ],
                [
                    'heading' => 'El registro vale como prueba si fue acordado',
                    'paragraphs' => [
                        'El contenido del registro electrónico hace prueba plena si se acredita que fue acordado entre la persona trabajadora y la empleadora. Por eso no basta con que el sistema guarde horas: conviene poder demostrar que la persona aceptó cómo se registra y que revisó su registro.',
                        'En la práctica eso significa guardar la aceptación del acuerdo de registro, no permitir que una marca se edite ni se borre (una corrección se agrega con su motivo) y que cada persona pueda ver y confirmar lo que se registró de ella.',
                    ],
                    'sources' => ['LFT, art. 132, fracción XXXIV, párrafo tercero'],
                ],
                [
                    'heading' => 'La jornada semanal baja de 48 a 40 horas en cinco años',
                    'paragraphs' => [
                        'La jornada ordinaria semanal máxima es de 48 horas en 2026, 46 en 2027, 44 en 2028, 42 en 2029 y 40 en 2030, a partir del 1 de enero de cada año. La reducción no puede bajar sueldos, salarios ni prestaciones.',
                        'La jornada diaria máxima sigue siendo de 8 horas la diurna, 7 la nocturna y 7 y media la mixta, y el horario puede distribuirse de común acuerdo.',
                    ],
                    'sources' => ['LFT, art. 59 y transitorio segundo', 'LFT, art. 61', 'LFT, art. 58', 'Transitorio séptimo'],
                ],
                [
                    'heading' => 'Horas extra: un tope semanal que también cambia',
                    'paragraphs' => [
                        'El tiempo extraordinario podrá distribuirse en hasta cuatro horas diarias, en un máximo de cuatro días por semana, y se paga un cien por ciento más. Su tope semanal es de 9 horas en 2026 y 2027, 10 en 2028, 11 en 2029 y 12 desde 2030.',
                        'Lo que supere ese tope se paga un doscientos por ciento más y no puede pasar de cuatro horas a la semana. La suma de la jornada ordinaria y la extraordinaria nunca puede ser mayor de doce horas en un día.',
                    ],
                    'sources' => ['LFT, art. 66 y transitorio cuarto', 'LFT, art. 68'],
                ],
                [
                    'heading' => 'La multa por no registrar',
                    'paragraphs' => [
                        'A la persona empleadora que no cumpla con el registro electrónico de jornada se le impone una multa de 250 a 5,000 veces la Unidad de Medida y Actualización (UMA). Aplica a quien la STPS determine como obligado.',
                    ],
                    'sources' => ['LFT, art. 994, fracción IV Bis'],
                ],
                [
                    'heading' => 'Qué conviene preparar antes de enero',
                    'paragraphs' => [
                        'Acordar con cada persona su horario y cómo va a marcar, decidir cómo se comprueba que marcó en su centro de trabajo, definir quién corrige un olvido y cómo queda el rastro, y tener a mano el reporte por centro y periodo para cuando la autoridad lo pida.',
                        'Conservar los registros mientras dure la relación de trabajo y al menos un año después es la lectura más prudente mientras la STPS no fije otro plazo.',
                    ],
                    'sources' => ['LFT, art. 132, fracción XXXIV', 'LFT, art. 804'],
                ],
            ],
        ];
    }
}
