<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of the buzón de quejas (/buzon-de-quejas). What it says of the law comes from docs/normas/nom-035/criterios.md,
 * section H (C-40 to C-49).
 *
 * @phpstan-import-type Page from ToolPages
 */
final class Complaints
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Buzón de quejas anónimo y canal de denuncias',
            'description' => 'Un canal de denuncias para tu empresa: tu gente presenta su queja desde el celular, anónima si quiere, y llega solo a quien debe atenderla.',
            'name' => 'Buzón de quejas',
            'h1' => 'Buzón de quejas anónimo para tu empresa',
            'lead' => 'Tu gente escanea el cartel del centro y presenta su queja desde el celular, sin crear una cuenta y de forma anónima si quiere. La queja llega solo a quien le toca atenderla, nunca a la persona señalada, y quien la presentó sigue el avance con su referencia.',
            'norm' => [
                'title' => 'Qué pide la NOM-035',
                'lead' => 'La NOM-035-STPS-2018 pide mecanismos seguros y confidenciales para recibir quejas y denuncias. No exige que sean en línea, anónimos ni con seguimiento: eso lo agrega Humetría.',
                'rules' => [
                    ['text' => 'Disponer de mecanismos seguros y confidenciales para recibir quejas por prácticas opuestas al entorno organizacional favorable y para denunciar actos de violencia laboral.', 'source' => 'NOM-035-STPS-2018, numeral 8.1, inciso b'],
                    ['text' => 'Las personas trabajadoras informan y denuncian a través de los mecanismos que establezca el patrón o de la comisión de seguridad e higiene.', 'source' => 'NOM-035-STPS-2018, numeral 6.4'],
                    ['text' => 'El aviso por escrito de un acontecimiento traumático severo lleva la fecha, el nombre de quien lo elabora, los involucrados si los hay, la fecha en que ocurrió y su descripción.', 'source' => 'NOM-035-STPS-2018, numeral 6.5'],
                    ['text' => 'La STPS verifica, por documento o entrevista, que el patrón dispone de esos mecanismos.', 'source' => 'NOM-035-STPS-2018, numeral 10.2'],
                ],
                'facts' => [
                    ['label' => 'Aplica a', 'value' => 'Todo centro de trabajo (NOM-035)'],
                    ['label' => 'Tipos de caso', 'value' => 'Queja, denuncia de violencia laboral y aviso de acontecimiento traumático'],
                    ['label' => 'Quién verifica', 'value' => 'La STPS (numeral 10.2)'],
                ],
            ],
            'features' => [
                ['title' => 'Desde el celular, sin cuenta', 'text' => 'Cada centro tiene su enlace y un cartel con código QR. La persona presenta su queja sin crear una cuenta, de forma anónima o confidencial, y recibe una referencia y una clave para seguirla.'],
                ['title' => 'Llega a quien le toca', 'text' => 'La persona dice contra quién es la queja por rol, nunca por nombre. Una queja contra RH no llega a RH, ni una contra la dirección a la dirección. Si no hay nadie independiente que la reciba, el formulario indica la vía externa.'],
                ['title' => 'Un aviso que no dice nada del caso', 'text' => 'El correo que recibe quien atiende el canal trae solo conteos, nunca de qué trata la queja. Quien la presentó puede dejar un correo para enterarse de que hay novedades, sin referencia ni contenido.'],
                ['title' => 'Seguimiento con plazos', 'text' => 'Quien atiende el caso lo pasa a revisión y lo resuelve, con los plazos de acuse y de respuesta que fija tu empresa. La persona ve el avance con su referencia y su clave.'],
                ['title' => 'Evidencia sin exponer los casos', 'text' => 'El canal tiene su procedimiento por escrito, sus personas encargadas y una página de evidencia imprimible que muestra cómo opera el canal, nunca el contenido de los casos.'],
            ],
            'audience' => [
                'company' => 'RH activa el canal y designa a quién lo atiende: personas de la empresa, la comisión de seguridad e higiene o alguien de fuera, como un abogado. Quien administra el canal ve conteos, no casos.',
                'consultant' => 'Si asesoras a varias empresas, activas y configuras el canal de cada una por separado desde la misma cuenta, cada una con sus propias personas encargadas.',
            ],
            'privacy' => 'Las páginas del canal no piden cuenta ni guardan la dirección de internet ni el navegador, y el contenido va cifrado. Ver un caso exige que te lo asignen: ni el dueño ni RH lo leen por administrar el canal. El correo de aviso nunca dice de qué trata la queja.',
            'faqs' => [
                ['question' => '¿La NOM-035 obliga a tener un buzón de quejas?', 'answer' => 'Pide disponer de mecanismos seguros y confidenciales para recibir quejas por prácticas opuestas al entorno organizacional favorable y denuncias de violencia laboral (numeral 8.1, inciso b). No exige que sea en línea ni anónimo.'],
                ['question' => '¿Las quejas pueden ser anónimas?', 'answer' => 'Sí. La persona no crea una cuenta, y las páginas del canal no guardan su dirección de internet ni su navegador. El aviso de un acontecimiento traumático severo es la excepción: la norma pide el nombre de quien lo escribe (numeral 6.5).'],
                ['question' => '¿Qué pasa si la queja es contra RH o contra el dueño?', 'answer' => 'No se asigna a quien tiene ese rol. Si no hay nadie independiente que pueda recibirla, el formulario no la acepta e indica la vía externa, la Dirección General de Inspección Federal del Trabajo. Para esos casos puedes designar a una persona de fuera.'],
                ['question' => '¿Cómo sabe la persona qué pasó con su queja?', 'answer' => 'Al enviarla recibe una referencia y una clave. Con ellas ve el avance y los mensajes de quien atiende el caso. Si dejó un correo, recibe un aviso de que hay novedades, sin nada del contenido.'],
                ['question' => '¿Quién puede atender el canal?', 'answer' => 'Las personas que designe la empresa: alguien de la empresa, quien está en la comisión de seguridad e higiene del centro o una persona externa, como un abogado o un representante sindical, que ve el canal y nada más de la plataforma.'],
                ['question' => '¿Cuánto tiempo se guardan las quejas?', 'answer' => 'El contenido de un caso cerrado se borra a los 24 meses del cierre, plazo que la empresa ajusta entre 12 y 60. Quien atiende el caso puede ponerlo en retención legal para que no se borre.'],
            ],
            'film' => [
                'name' => 'humetria-complaints',
                'title' => 'Buzón de quejas en Humetría',
                'description' => 'La persona escanea el cartel del centro y presenta su queja anónima desde el celular, la queja llega solo a quien le toca y nunca a RH si es contra RH, el aviso por correo trae solo conteos y la encargada pasa el caso a revisión mientras la persona ve el avance con su referencia.',
                'duration' => 28,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
