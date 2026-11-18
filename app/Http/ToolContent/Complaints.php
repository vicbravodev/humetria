<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of Buzón de quejas (/buzon-de-quejas). What it says of the law comes from docs/normas/nom-035/ (sección H) and app/Modules/Complaints; its
 * screenshots are the demo company's, in public/images/tools.
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
            'family' => 'Escucha a tu gente',
            'chip' => 'El mecanismo de quejas que pide la NOM-035 · numeral 8.1',
            'h1' => 'Un buzón de quejas al que tu gente sí se atreve a escribir',
            'lead' => 'Tu gente escanea el cartel del centro y presenta su queja desde el celular, sin crear una cuenta y anónima si quiere. La queja llega solo a quien le toca atenderla, nunca a la persona señalada, y quien la presentó sigue el avance con su referencia.',
            'proof' => 'No pide cuenta ni guarda la dirección de internet de quien escribe. La queja nunca llega a la persona señalada y el aviso por correo no dice de qué trata.',
            'heroPhone' => 'complaints-10-public-form-mobile',
            'phoneAlt' => 'El formulario público del canal en el celular',
            'norm' => [
                'title' => 'Qué pide la norma',
                'lead' => 'La NOM-035 pide mecanismos seguros y confidenciales para recibir quejas y denuncias de violencia laboral. No exige que sea en línea ni anónimo: eso lo pone Humetría.',
                'facts' => [
                    [
                        'label' => 'Aplica a',
                        'value' => 'Todo centro de trabajo',
                        'note' => 'NOM-035-STPS-2018',
                    ],
                    [
                        'label' => 'Tipos de caso',
                        'value' => 'Queja, denuncia y aviso',
                        'note' => 'Incluye acontecimiento traumático',
                    ],
                    [
                        'label' => 'Quién verifica',
                        'value' => 'La STPS',
                        'note' => 'Por documento o entrevista · 10.2',
                    ],
                ],
                'rules' => [
                    [
                        'text' => 'Disponer de mecanismos seguros y confidenciales para recibir quejas por prácticas opuestas al entorno organizacional favorable y denuncias de violencia laboral.',
                        'source' => 'NOM-035, numeral 8.1 b)',
                    ],
                    [
                        'text' => 'Las personas informan y denuncian a través de los mecanismos que establezca el patrón o de la comisión de seguridad e higiene.',
                        'source' => 'NOM-035, numeral 6.4',
                    ],
                    [
                        'text' => 'El aviso de un acontecimiento traumático severo lleva la fecha, quién lo elabora, los involucrados, cuándo ocurrió y su descripción.',
                        'source' => 'NOM-035, numeral 6.5',
                    ],
                    [
                        'text' => 'La STPS verifica que el patrón dispone de esos mecanismos.',
                        'source' => 'NOM-035, numeral 10.2',
                    ],
                ],
                'note' => null,
            ],
            'guideLink' => false,
            'pick' => null,
            'steps' => [
                [
                    'title' => 'Pegas el cartel del centro',
                    'text' => 'Cada centro tiene su enlace y su cartel con código QR. Defines quién atiende el canal y sus plazos.',
                ],
                [
                    'title' => 'La persona presenta su queja',
                    'text' => 'Desde su celular, anónima o confidencial. Dice contra quién es por rol, nunca por nombre, y recibe una referencia y una clave.',
                ],
                [
                    'title' => 'Quien le toca la atiende',
                    'text' => 'La pasa a revisión, conversa con la persona por el canal y la resuelve dentro de los plazos.',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'Bandeja de casos',
                    'title' => 'Cada caso, con sus plazos a la vista',
                    'text' => 'Quien atiende el canal ve qué espera acuse, qué está en revisión y qué vence pronto.',
                    'bullets' => [
                        'Plazos de acuse y de respuesta que fija tu empresa',
                        'Ver un caso exige que te lo asignen',
                        'Retención por un caso abierto ante una autoridad',
                    ],
                    'shot' => 'complaints-01-index',
                    'alt' => 'Bandeja de casos con su estado',
                    'caption' => 'Un caso espera acuse. Tres en revisión.',
                ],
                [
                    'kicker' => 'Seguimiento',
                    'title' => 'Conversación en dos vías, sin revelar a nadie',
                    'text' => 'Quien atiende el caso escribe y la persona responde con su referencia y su clave, sin dar su nombre.',
                    'bullets' => [
                        'Estado del caso en pasos claros',
                        'Notas internas que la persona no ve',
                        'El contenido del caso va cifrado',
                    ],
                    'shot' => 'complaints-02-case',
                    'alt' => 'Detalle de un caso en revisión con su línea de tiempo',
                    'caption' => 'Pasos del caso. Plazos y notas internas.',
                ],
                [
                    'kicker' => 'Desde el celular',
                    'title' => 'Una queja desde el celular, sin crear una cuenta',
                    'text' => 'La persona escanea el cartel, elige el tipo de caso, cuenta lo que pasó y recibe su referencia.',
                    'bullets' => [
                        'Anónima o confidencial, ella decide',
                        'Si es contra RH o la dirección, no llega ahí',
                        'Si no hay nadie independiente, indica la vía externa',
                    ],
                    'shot' => 'complaints-10-public-form-mobile',
                    'alt' => 'Formulario público del canal en el celular',
                    'caption' => 'Sin cuenta ni contraseña. Referencia y clave para seguirla.',
                ],
            ],
            'beforeAfterTitle' => 'Del buzón de madera al canal que la gente usa',
            'beforeAfter' => [
                [
                    'before' => 'Un buzón físico que nadie abre o todos ven',
                    'after' => 'Un canal en el celular, con cartel por centro',
                ],
                [
                    'before' => 'Quejas que llegan a la persona señalada',
                    'after' => 'Llegan solo a quien le toca, por rol',
                ],
                [
                    'before' => 'Sin saber qué pasó con la queja',
                    'after' => 'Referencia y clave para ver el avance',
                ],
                [
                    'before' => 'Correos que cuentan el caso a todos',
                    'after' => 'Avisos con solo conteos, sin contenido',
                ],
            ],
            'audience' => [
                [
                    'who' => 'Quien atiende el canal',
                    'title' => 'Atiende cada caso con método',
                    'text' => 'Alguien de la empresa, de la comisión o una persona externa, como un abogado.',
                ],
                [
                    'who' => 'RH y dirección',
                    'title' => 'Ven que el canal opera, no los casos',
                    'text' => 'Una página de evidencia muestra cómo opera el canal, nunca el contenido.',
                ],
                [
                    'who' => 'La persona trabajadora',
                    'title' => 'Se atreve a hablar',
                    'text' => 'Sin cuenta, anónima si quiere, y con forma de saber qué pasó.',
                ],
            ],
            'privacy' => [
                'title' => 'Si la persona elige anónima, Humetría no sabe quién escribió',
                'items' => [
                    [
                        'title' => 'Sin cuenta ni rastro guardado',
                        'text' => 'Las páginas del canal no piden cuenta ni guardan la dirección de internet ni el navegador. Lo que la persona cuenta sí puede identificarla: el formulario se lo recuerda.',
                    ],
                    [
                        'title' => 'Contenido cifrado',
                        'text' => 'Ver un caso exige que te lo asignen: ni el dueño ni RH lo leen por administrar el canal.',
                    ],
                    [
                        'title' => 'Avisos sin contenido',
                        'text' => 'El correo de aviso trae solo conteos, nunca de qué trata la queja.',
                    ],
                    [
                        'title' => 'Se borra a su tiempo',
                        'text' => 'El contenido de un caso cerrado se borra a los 24 meses, plazo que la empresa ajusta.',
                    ],
                ],
            ],
            'faqs' => [
                [
                    'question' => '¿La NOM-035 obliga a tener un buzón de quejas?',
                    'answer' => 'Pide mecanismos seguros y confidenciales para recibir quejas y denuncias de violencia laboral (numeral 8.1 b). No exige que sea en línea ni anónimo.',
                ],
                [
                    'question' => '¿Las quejas pueden ser anónimas?',
                    'answer' => 'Sí. La persona no crea una cuenta y las páginas no guardan su dirección de internet ni su navegador.',
                ],
                [
                    'question' => '¿Qué pasa si la queja es contra RH o contra el dueño?',
                    'answer' => 'No se asigna a quien tiene ese rol. Si no hay nadie independiente, el formulario indica la vía externa.',
                ],
                [
                    'question' => '¿Cómo sabe la persona qué pasó con su queja?',
                    'answer' => 'Con su referencia y su clave ve el avance y los mensajes de quien atiende el caso.',
                ],
                [
                    'question' => '¿Quién puede atender el canal?',
                    'answer' => 'Quien designe la empresa: alguien interno, de la comisión de seguridad e higiene o externo, como un abogado.',
                ],
                [
                    'question' => '¿Cuánto tiempo se guardan las quejas?',
                    'answer' => 'El contenido de un caso cerrado se borra a los 24 meses, plazo que la empresa ajusta entre 12 y 60.',
                ],
            ],
            'related' => [
                [
                    'key' => 'nom_035',
                    'why' => 'El buzón es parte de lo que pide la norma.',
                ],
                [
                    'key' => 'engagement',
                    'why' => 'Escucha a tu gente antes de que haya quejas.',
                ],
                [
                    'key' => 'org_chart',
                    'why' => 'Las quejas se dirigen por rol, no por nombre.',
                ],
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
