<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of the registro de jornada (/registro-de-jornada), the one the Google Ads campaign lands on. What it says of
 * the law comes from docs/normas/jornada/criterios.md.
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
            'h1' => 'Registro electrónico de jornada, listo para el 1 de enero de 2027',
            'lead' => 'Tu gente marca entrada, pausas y salida desde su celular, sin reloj checador ni contraseña. El registro no se puede alterar, cada persona confirma su semana y RH arma con un botón el reporte para la autoridad.',
            'norm' => [
                'title' => 'Qué pide la ley',
                'lead' => 'La reforma a la Ley Federal del Trabajo publicada en el DOF el 1 de mayo de 2026 agrega la obligación de registrar la jornada y reduce la jornada semanal año con año.',
                'rules' => [
                    ['text' => 'Registrar de manera electrónica la jornada de cada persona trabajadora, con su hora de inicio y de fin, y entregarlo a la autoridad cuando lo pida.', 'source' => 'LFT, art. 132, fracción XXXIV'],
                    ['text' => 'El registro hace prueba plena si se acredita que fue acordado entre la persona trabajadora y la empleadora.', 'source' => 'LFT, art. 132, fracción XXXIV, párrafo tercero'],
                    ['text' => 'La jornada ordinaria semanal máxima baja de 48 horas en 2026 a 46 en 2027, y sigue bajando hasta 40 en 2030.', 'source' => 'LFT, art. 59 y transitorio segundo'],
                    ['text' => 'La STPS publicará las reglas sobre a quién aplica y sus excepciones. Mientras no lo haga, la ley no excluye al personal de confianza, a quien trabaja a distancia ni a las empresas pequeñas.', 'source' => 'LFT, art. 132, fracción XXXIV, párrafo segundo; transitorio quinto'],
                ],
                'facts' => [
                    ['label' => 'Obligatorio desde', 'value' => '1 de enero de 2027'],
                    ['label' => 'Multa por no cumplir', 'value' => 'De 250 a 5,000 UMA (art. 994, fracción IV Bis)'],
                    ['label' => 'Jornada semanal en 2027', 'value' => '46 horas'],
                ],
            ],
            'features' => [
                ['title' => 'Marca desde el celular', 'text' => 'Cada persona vincula su teléfono una sola vez y marca entrada, pausas y salida con su huella o su rostro, sin correo ni contraseña. La hora la pone el servidor, nunca el reloj del teléfono.'],
                ['title' => 'Prueba de que marcó en el centro', 'text' => 'Con el código QR del centro y su zona en el mapa, cada marca guarda cómo se hizo. Para obras, rutas y visitas, el centro marca en campo y se guarda la ubicación.'],
                ['title' => 'Un registro que no se puede alterar', 'text' => 'Las marcas no se editan ni se borran: una corrección es una marca nueva con autor y motivo. Cada semana la persona confirma u objeta su registro, y eso acredita el acuerdo que pide la ley.'],
                ['title' => 'Topes a la vista', 'text' => 'Avisa antes de rebasar la jornada semanal del año, las 4 horas extra en un día o el tope semanal de horas extra, y separa el tiempo doble del triple.'],
                ['title' => 'El reporte con un botón', 'text' => 'Por centro y periodo: persona, fecha, inicio y fin, pausas, horas ordinarias y extra, correcciones con su motivo y la confirmación de cada semana.'],
            ],
            'audience' => [
                'company' => 'RH configura horarios y centros, ve quién marcó y quién no, y resuelve las correcciones. El personal de confianza registra igual, desde el navegador de su computadora.',
                'consultant' => 'Si asesoras a varias empresas, llevas el registro de cada una por separado desde la misma cuenta y les entregas el reporte de cada centro.',
            ],
            'privacy' => 'La ubicación solo se toma al marcar, nunca en segundo plano ni entre marcas, y solo la ven los roles autorizados. Cada persona ve siempre su propio registro y sus correcciones.',
            'faqs' => [
                ['question' => '¿Desde cuándo es obligatorio el registro electrónico de jornada?', 'answer' => 'Desde el 1 de enero de 2027, según el transitorio quinto de la reforma a la Ley Federal del Trabajo publicada en el DOF el 1 de mayo de 2026.'],
                ['question' => '¿A quién le toca registrar su jornada?', 'answer' => 'La ley no excluye a nadie: la STPS publicará las reglas de aplicación y sus excepciones. Mientras tanto, Humetría supone que todas las personas trabajadoras registran, incluido el personal de confianza, y una excepción posterior se aplica cambiando la política de la empresa.'],
                ['question' => '¿Necesito comprar un reloj checador?', 'answer' => 'No. Cada persona marca desde su propio celular, con el código QR del centro o dentro de su zona en el mapa. Si alguien no tiene celular, el centro puede tener un kiosco en una tableta.'],
                ['question' => '¿Cómo se acredita que el registro fue acordado?', 'answer' => 'Antes de su primera marca la persona acepta el acuerdo de registro electrónico, y cada semana confirma u objeta su registro. Las dos evidencias quedan guardadas con su versión y su fecha.'],
                ['question' => '¿Qué pasa si alguien olvida marcar?', 'answer' => 'Pide una corrección desde su celular y RH la aprueba. La corrección es una marca nueva con autor y motivo: la marca original nunca se edita ni se borra.'],
                ['question' => '¿Cuánto es la multa por no registrar la jornada?', 'answer' => 'De 250 a 5,000 veces la Unidad de Medida y Actualización (UMA) por incumplir el art. 132, fracción XXXIV, según el art. 994, fracción IV Bis de la Ley Federal del Trabajo.'],
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
