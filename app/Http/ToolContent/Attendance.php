<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of Registro de jornada (/registro-de-jornada). What it says of the law comes from docs/normas/jornada/criterios.md; its
 * screenshots are the demo company's, in public/images/tools.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class Attendance
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Registro electrónico de jornada para 2027',
            'description' => 'Desde el 1 de enero de 2027 la LFT pide registrar de forma electrónica la jornada de cada persona. Tu gente marca desde su celular y tú tienes el reporte.',
            'name' => 'Registro de jornada',
            'family' => 'Cumple la norma',
            'chip' => 'Obligatorio desde el 1 de enero de 2027 · LFT, art. 132, fr. XXXIV',
            'h1' => 'El registro de jornada que pide la ley, desde el celular de tu gente',
            'lead' => 'Desde 2027 la ley pide registrar de forma electrónica el inicio y el fin de la jornada de cada persona. Tu gente marca desde su teléfono, sin reloj checador; tú tienes un registro que no se puede alterar y el reporte para la autoridad con un botón.',
            'proof' => 'La hora la pone el servidor, ninguna marca se edita ni se borra, y cada persona confirma su semana.',
            'norm' => [
                'title' => 'Qué pide la ley',
                'lead' => 'La reforma a la Ley Federal del Trabajo del 1 de mayo de 2026 obliga a registrar la jornada y reduce la jornada semanal año con año.',
                'facts' => [
                    [
                        'label' => 'Obligatorio desde',
                        'value' => '1 de enero de 2027',
                        'note' => 'Transitorio quinto',
                    ],
                    [
                        'label' => 'Jornada semanal máxima en 2027',
                        'value' => '46 horas',
                        'note' => 'Art. 59 y transitorio segundo',
                    ],
                    [
                        'label' => 'Si no se registra',
                        'value' => 'Multa de 250 a 5,000 UMA',
                        'note' => 'Art. 994, fracción IV Bis',
                    ],
                ],
                'rules' => [
                    [
                        'text' => 'Registrar de forma electrónica la jornada de cada persona, con su hora de inicio y de fin, y entregarlo a la autoridad cuando lo pida.',
                        'source' => 'LFT, art. 132, fracción XXXIV',
                    ],
                    [
                        'text' => 'El registro vale como prueba plena si se acredita que lo acordaron la persona trabajadora y la empresa.',
                        'source' => 'Art. 132, fracción XXXIV, párrafo tercero',
                    ],
                    [
                        'text' => 'Las horas extra se pagan al doble hasta el tope semanal del año y al triple lo que lo rebase, hasta 4 horas a la semana.',
                        'source' => 'LFT, arts. 66 y 68; transitorio cuarto',
                    ],
                    [
                        'text' => 'Mientras la STPS no publique a quién aplica, la ley no excluye al personal de confianza, a quien trabaja a distancia ni a las empresas pequeñas.',
                        'source' => 'Art. 132, fracción XXXIV, párrafo segundo',
                    ],
                ],
                'note' => 'La STPS todavía no publica el formato del reporte. Cuando lo haga, el cambio se aplica en la política de tu empresa, no en tu operación.',
            ],
            'guideLink' => true,
            'pick' => [
                'title' => '¿Cuánto puede trabajar tu gente cada año?',
                'lead' => 'La jornada máxima baja dos horas cada 1 de enero hasta 2030, y el tope de horas extra sube. Humetría aplica la tabla del año sola.',
                'question' => 'Elige el año',
                'options' => [
                    [
                        'label' => '2026',
                        'answer' => '48 h a la semana',
                        'detail' => 'Hasta 9 horas extra a la semana, pagadas al doble.',
                    ],
                    [
                        'label' => '2027',
                        'answer' => '46 h a la semana',
                        'detail' => 'Hasta 9 horas extra a la semana, pagadas al doble. Empieza el registro electrónico.',
                    ],
                    [
                        'label' => '2028',
                        'answer' => '44 h a la semana',
                        'detail' => 'Hasta 10 horas extra a la semana, pagadas al doble.',
                    ],
                    [
                        'label' => '2029',
                        'answer' => '42 h a la semana',
                        'detail' => 'Hasta 11 horas extra a la semana, pagadas al doble.',
                    ],
                    [
                        'label' => '2030',
                        'answer' => '40 h a la semana',
                        'detail' => 'Hasta 12 horas extra a la semana, pagadas al doble.',
                    ],
                ],
            ],
            'steps' => [
                [
                    'title' => 'Configura centros y horarios',
                    'text' => 'Cada centro con su código QR y su zona en el mapa, o en campo para obras y rutas. Cada persona con su horario acordado.',
                    'shot' => 'attendance-05-schedules',
                    'alt' => 'Los horarios de cada persona, con su jornada de la semana',
                ],
                [
                    'title' => 'Cada persona vincula su teléfono',
                    'text' => 'Recibe su enlace, acepta el acuerdo de registro y crea su llave de acceso. Desde ahí marca en segundos.',
                    'shot' => 'attendance-10-punch-mobile',
                    'alt' => 'La pantalla Marcar en el celular de una persona',
                ],
                [
                    'title' => 'Revisas y entregas',
                    'text' => 'Cada semana la persona confirma su registro, tú resuelves las correcciones y descargas el reporte del centro.',
                    'shot' => 'attendance-03-report',
                    'alt' => 'El reporte para la autoridad de un centro',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'La semana de cada persona',
                    'title' => 'Sabes quién trabajó cuánto, sin perseguir hojas de firma',
                    'text' => 'Las horas de la semana por persona, con su centro y su puesto, y los avisos antes de que una semana se pase de los topes del año.',
                    'bullets' => [
                        'Avisa al acercarse a la jornada semanal, a 4 horas extra en un día o al tope de horas extra',
                        'Separa el tiempo doble del triple con el tope de cada año',
                        'Ves quién ya confirmó su semana y quién la objetó',
                    ],
                    'shot' => 'attendance-02-people',
                    'alt' => 'Tabla de personas con sus horas de la semana',
                    'caption' => 'Persona por persona: las horas de cada día de la semana y si ya la confirmó.',
                ],
                [
                    'kicker' => 'Correcciones',
                    'title' => 'Un olvido se corrige sin borrar nada',
                    'text' => 'Quien olvidó marcar pide la corrección desde su celular. RH la aprueba y la corrección queda como una marca nueva, con autor y motivo.',
                    'bullets' => [
                        'La marca original nunca se edita ni se borra',
                        'Cada cambio dice quién lo pidió, quién lo aprobó y por qué',
                        'La persona ve sus correcciones en su propio registro',
                    ],
                    'shot' => 'attendance-04-corrections',
                    'alt' => 'Cola de correcciones con la línea del día',
                    'caption' => 'Una corrección pendiente, con la línea del día marca por marca.',
                ],
                [
                    'kicker' => 'Para la autoridad',
                    'title' => 'El reporte, por centro y periodo, con un botón',
                    'text' => 'Persona, fecha, inicio y fin, pausas, horas ordinarias y extra, correcciones con su motivo y la confirmación de cada semana.',
                    'bullets' => [
                        'Avisos de lo que conviene revisar antes de entregarlo',
                        'En pantalla hasta 93 días, en CSV hasta 366',
                        'Cada consulta y cada descarga quedan en la bitácora',
                    ],
                    'shot' => 'attendance-03-report',
                    'alt' => 'Reporte para la autoridad de Planta Querétaro',
                    'caption' => 'El reporte de la semana de un centro, con lo que conviene revisar antes de entregarlo.',
                ],
            ],
            'beforeAfterTitle' => 'Del reloj checador al registro que pide la ley',
            'beforeAfter' => [
                [
                    'before' => 'Un reloj checador que hay que comprar, instalar y mantener en cada centro',
                    'after' => 'El celular de cada persona, con la llave que ya usa para desbloquearlo',
                ],
                [
                    'before' => 'Hojas de firma o un Excel que cualquiera puede cambiar',
                    'after' => 'Marcas que nadie edita ni borra',
                ],
                [
                    'before' => 'Sin forma de probar que la persona estuvo de acuerdo',
                    'after' => 'La aceptación del acuerdo y la confirmación de cada semana',
                ],
                [
                    'before' => 'Las horas extra se descubren en la nómina',
                    'after' => 'Avisos de topes antes de rebasarlos',
                ],
            ],
            'audience' => [
                [
                    'who' => 'RH',
                    'title' => 'Ve quién marcó y resuelve lo que falta',
                    'text' => 'Configura centros y horarios, resuelve correcciones y entrega el reporte. El personal de confianza registra igual, desde su computadora.',
                ],
                [
                    'who' => 'Consultores',
                    'title' => 'Lleva el registro de cada cliente',
                    'text' => 'Cada empresa con sus datos aparte, desde tu misma cuenta, con el reporte de cada centro.',
                ],
                [
                    'who' => 'La persona trabajadora',
                    'title' => 'Marca en segundos y ve su registro',
                    'text' => 'Sin correo ni contraseña. Ve cada marca y sus correcciones, y confirma u objeta su semana.',
                ],
            ],
            'privacy' => [
                'title' => 'Lo que se registra, y lo que no',
                'items' => [
                    [
                        'title' => 'Su huella no sale de su teléfono',
                        'text' => 'La persona confirma con la huella, el rostro o el PIN con que desbloquea su teléfono. Humetría nunca la recibe ni la guarda.',
                    ],
                    [
                        'title' => 'La ubicación, solo al marcar',
                        'text' => 'Nunca en segundo plano ni entre marcas, y solo la ven los roles autorizados.',
                    ],
                    [
                        'title' => 'La hora es la del servidor',
                        'text' => 'El reloj del teléfono no cuenta: nadie adelanta ni atrasa una marca.',
                    ],
                    [
                        'title' => 'Cada persona ve su registro',
                        'text' => 'Con el detalle de cada marca, sus correcciones y su confirmación semanal.',
                    ],
                ],
            ],
            'faqs' => [
                [
                    'question' => '¿Desde cuándo es obligatorio el registro electrónico de jornada?',
                    'answer' => 'Desde el 1 de enero de 2027, según el transitorio quinto de la reforma a la Ley Federal del Trabajo publicada en el DOF el 1 de mayo de 2026.',
                ],
                [
                    'question' => '¿A quién le toca registrar su jornada?',
                    'answer' => 'La ley no excluye a nadie y la STPS publicará las excepciones. Mientras tanto, Humetría supone que todas las personas registran, incluido el personal de confianza.',
                ],
                [
                    'question' => '¿Necesito comprar un reloj checador?',
                    'answer' => 'No. Cada persona marca desde su celular, con el código QR del centro o dentro de su zona en el mapa. Si alguien no tiene celular, el centro puede usar un kiosco en una tableta.',
                ],
                [
                    'question' => '¿La empresa ve la huella de su gente?',
                    'answer' => 'No. La huella o el rostro solo desbloquean la llave de acceso en el teléfono de la persona; Humetría nunca los recibe.',
                ],
                [
                    'question' => '¿Qué pasa si alguien olvida marcar?',
                    'answer' => 'Pide una corrección desde su celular y RH la aprueba. Es una marca nueva con autor y motivo; la original nunca se edita.',
                ],
                [
                    'question' => '¿Cuánto es la multa por no registrar la jornada?',
                    'answer' => 'De 250 a 5,000 veces la UMA, según el artículo 994, fracción IV Bis de la Ley Federal del Trabajo.',
                ],
            ],
            'related' => [
                [
                    'key' => 'leave',
                    'why' => 'Los días de vacaciones no cuentan como faltas.',
                ],
                [
                    'key' => 'nom_037',
                    'why' => 'Quien teletrabaja marca desde su lugar convenido.',
                ],
                [
                    'key' => 'org_chart',
                    'why' => 'Cada jefe ve la jornada de su gente.',
                ],
            ],
            'film' => [
                'name' => 'humetria-attendance',
                'title' => 'Registro de jornada en Humetría',
                'description' => 'La persona marca desde su celular con el código QR del centro, el registro no se puede alterar, confirma su semana con los topes a la vista y RH genera con un botón el reporte para la autoridad.',
                'duration' => 28,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
