<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The public page of NOM-037 (/nom-037). What it says of the law comes from docs/normas/nom-037/; its
 * screenshots are the demo company's, in public/images/tools.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class Nom037
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'NOM-037 teletrabajo: listado, política y verificación',
            'description' => 'Lleva la NOM-037 de teletrabajo: el listado de personas, la lista de verificación desde el celular, la política y el aviso de cada verificación.',
            'name' => 'NOM-037',
            'family' => 'Cumple la norma',
            'chip' => 'NOM-037-STPS-2023 · en vigor desde el 5 de diciembre de 2023',
            'h1' => 'El teletrabajo en orden: listado, lugares validados y política',
            'lead' => 'Llevas el listado de quien teletrabaja, cada persona revisa su lugar de trabajo desde el celular y la Comisión de Seguridad e Higiene lo valida. La política se arma contestando preguntas y Humetría te avisa cuando toca la siguiente verificación.',
            'proof' => 'Parte de las preguntas literales del Apéndice 3, la Comisión de Seguridad e Higiene valida cada lugar y el expediente se ordena como la tabla 10.3 de la norma.',
            'heroPhone' => 'nom-037-10-portal-mobile',
            'phoneAlt' => 'La sección de teletrabajo del portal en el celular',
            'norm' => [
                'title' => 'Qué pide la norma',
                'lead' => 'Si alguien trabaja más del 40 % de su tiempo desde su casa o el lugar que elija, la NOM-037 pide condiciones de seguridad y salud también ahí.',
                'facts' => [
                    [
                        'label' => 'En vigor desde',
                        'value' => '5 de diciembre de 2023',
                        'note' => 'DOF 8 de junio de 2023',
                    ],
                    [
                        'label' => 'A quién aplica',
                        'value' => 'Más del 40 % de la jornada',
                        'note' => 'LFT, art. 330-A',
                    ],
                    [
                        'label' => 'Evidencias',
                        'value' => 'Al menos un año',
                        'note' => 'Impresas o digitales · 10.4',
                    ],
                ],
                'rules' => [
                    [
                        'text' => 'Llevar un listado de las personas en teletrabajo con sus datos, sus lugares de trabajo convenidos y el equipo otorgado.',
                        'source' => 'Numeral 5.1 y tabla 10.3',
                    ],
                    [
                        'text' => 'Tener una Política de Teletrabajo y difundirla a todo el centro, incluidas las personas que no teletrabajan.',
                        'source' => 'Numeral 5.2',
                    ],
                    [
                        'text' => 'Cada lugar se revisa con una lista de verificación que valida la Comisión de Seguridad e Higiene, y una segunda versión se aplica periódicamente.',
                        'source' => 'Numerales 5.4, 5.5 y 5.5.1',
                    ],
                    [
                        'text' => 'Entregar el equipo de cómputo y ergonómico, y capacitar a las personas al menos una vez al año.',
                        'source' => 'Numerales 5.7 y 5.9',
                    ],
                ],
                'note' => 'El trabajo ocasional o esporádico desde casa no cuenta como teletrabajo.',
            ],
            'guideLink' => false,
            'pick' => null,
            'steps' => [
                [
                    'title' => 'Agregas a quien teletrabaja',
                    'text' => 'Según el porcentaje de su jornada, con los datos que pide la norma. El listado guarda cada alta y baja.',
                ],
                [
                    'title' => 'Cada persona revisa su lugar',
                    'text' => 'Contesta la lista de verificación desde su celular, por cada lugar donde trabaja, con foto si hay un riesgo.',
                ],
                [
                    'title' => 'La Comisión valida',
                    'text' => 'Valida el lugar, lo valida con un plan de acción o no lo valida. Humetría avisa cuando la validación está por vencer.',
                ],
            ],
            'features' => [
                [
                    'kicker' => 'El centro de un vistazo',
                    'title' => 'Qué te falta en cada centro, en una pantalla',
                    'text' => 'El avance del centro contra lo que pide la norma, con el listado de teletrabajo y lo pendiente de cada persona.',
                    'bullets' => [
                        'El listado con la fecha de cada alta y baja',
                        'El equipo entregado, con su acuse',
                        'Las capacitaciones del año, por persona',
                    ],
                    'shot' => 'nom-037-02-center',
                    'alt' => 'Avance de NOM-037 en un centro y su listado',
                    'caption' => 'Avance del centro: 6 de 16. Quién teletrabaja y desde dónde.',
                ],
                [
                    'kicker' => 'Lista de verificación',
                    'title' => 'Cada lugar, revisado y validado',
                    'text' => 'La persona contesta y la Comisión revisa cada respuesta. Si algo no está bien, se valida con un plan de acción o no se valida.',
                    'bullets' => [
                        'Las preguntas del Apéndice 3, que la Comisión puede ajustar',
                        'Si una respuesta señala un riesgo, se pide describirlo y una foto',
                        'Eliges cada cuánto se verifica: 3, 6, 12 o 24 meses',
                    ],
                    'shot' => 'nom-037-04-checklist-review',
                    'alt' => 'Resultado de una lista de verificación revisada por la Comisión',
                    'caption' => 'Decisión de la Comisión. Respuesta por respuesta.',
                ],
                [
                    'kicker' => 'Portal de la persona',
                    'title' => 'La persona revisa su lugar y confirma la política desde su celular',
                    'text' => 'La política se arma contestando ocho preguntas o subiendo tu PDF. Al publicarla llega a todo el centro, y cada persona la confirma desde su portal.',
                    'bullets' => [
                        'Registra quién confirmó haberla leído',
                        'La persona la confirma desde su portal',
                        'Queda en el expediente del centro',
                    ],
                    'shot' => 'nom-037-10-portal-mobile',
                    'alt' => 'Avisos y listas en el portal de teletrabajo',
                    'caption' => 'Lo que la persona ve en su portal. Confirma con su llave.',
                ],
            ],
            'beforeAfterTitle' => 'Del correo con un PDF al expediente de cada centro',
            'beforeAfter' => [
                [
                    'before' => 'Un Excel con quién trabaja desde casa',
                    'after' => 'El listado con altas, bajas y equipo entregado',
                ],
                [
                    'before' => 'Fotos del lugar por WhatsApp',
                    'after' => 'La lista de verificación desde el celular, con foto',
                ],
                [
                    'before' => 'La política enviada por correo, sin saber quién la leyó',
                    'after' => 'Acuse de lectura de cada persona',
                ],
                [
                    'before' => 'Revisiones que se olvidan',
                    'after' => 'Avisos cuando una validación está por vencer',
                ],
            ],
            'audience' => [
                [
                    'who' => 'RH',
                    'title' => 'Todo el teletrabajo, por centro',
                    'text' => 'El listado, la política, el equipo y las capacitaciones, con lo que falta a la vista.',
                ],
                [
                    'who' => 'Comisión de Seguridad e Higiene',
                    'title' => 'Valida cada lugar con su evidencia',
                    'text' => 'Revisa las respuestas y las fotos, y decide si el lugar es apto.',
                ],
                [
                    'who' => 'La persona trabajadora',
                    'title' => 'Revisa su lugar desde el celular',
                    'text' => 'Contesta la lista, confirma la política y ve sus avisos en su portal.',
                ],
            ],
            'privacy' => [
                'title' => 'Datos del hogar, solo para quien los necesita',
                'items' => [
                    [
                        'title' => 'Solo el dueño, RH y la Comisión',
                        'text' => 'El domicilio, el teléfono y las fotos del lugar no los ve nadie más.',
                    ],
                    [
                        'title' => 'La salud, con consentimiento',
                        'text' => 'Lo que revela la salud se contesta con consentimiento expreso.',
                    ],
                    [
                        'title' => 'Lleva nombre, por la norma',
                        'text' => 'La lista es identificada porque la Comisión necesita saber de quién es el lugar.',
                    ],
                    [
                        'title' => 'Bitácora',
                        'text' => 'Quién vio y quién decidió cada lugar queda registrado.',
                    ],
                ],
            ],
            'faqs' => [
                [
                    'question' => '¿A quién aplica la NOM-037?',
                    'answer' => 'A quien trabaja más del 40 % del tiempo en su domicilio o en el lugar que elija, bajo la modalidad de teletrabajo (LFT, art. 330-A).',
                ],
                [
                    'question' => '¿Quién decide si un lugar de teletrabajo es apto?',
                    'answer' => 'La Comisión de Seguridad e Higiene, que valida las condiciones declaradas en la lista de verificación (numerales 4.27 y 5.5).',
                ],
                [
                    'question' => '¿Cada cuánto se revisa el lugar?',
                    'answer' => 'La norma pide una segunda versión de la lista para aplicarla periódicamente y deja al patrón fijar la periodicidad. En Humetría la eliges tú.',
                ],
                [
                    'question' => '¿Es obligatorio usar la lista del Apéndice 3?',
                    'answer' => 'No. El Apéndice 3 es informativo y el patrón puede ajustarlo. Humetría parte de sus preguntas literales.',
                ],
                [
                    'question' => '¿Qué equipo debe dar la empresa?',
                    'answer' => 'Equipo de cómputo, una silla ergonómica u otra apropiada, insumos y aditamentos (numeral 5.7).',
                ],
                [
                    'question' => '¿Cada cuánto hay que capacitar?',
                    'answer' => 'Al menos una vez al año (numeral 5.9), sobre los peligros del lugar, la política y el manejo de las tecnologías.',
                ],
            ],
            'related' => [
                [
                    'key' => 'attendance',
                    'why' => 'Quien teletrabaja también registra su jornada.',
                ],
                [
                    'key' => 'nom_035',
                    'why' => 'La otra evaluación de la STPS por centro.',
                ],
                [
                    'key' => 'ley_silla',
                    'why' => 'Asientos y pausas en el centro de trabajo.',
                ],
            ],
            'film' => [
                'name' => 'humetria-nom-037',
                'title' => 'NOM-037 en Humetría',
                'description' => 'RH agrega a una persona al listado de teletrabajo según su porcentaje de jornada, la persona contesta desde el celular la lista de verificación de su lugar de trabajo, RH arma y publica la política contestando preguntas y elige cada cuánto se verifica, con un aviso cuando un lugar está por vencer.',
                'duration' => 29,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
