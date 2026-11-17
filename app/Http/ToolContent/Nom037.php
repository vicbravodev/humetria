<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of the NOM-037 (/nom-037). What it says of the norm comes from docs/normas/nom-037/criterios.md and
 * docs/normas/nom-037/texto-de-referencia.md.
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
            'h1' => 'NOM-037 de teletrabajo, con el expediente de cada centro al día',
            'lead' => 'Llevas el listado de quien teletrabaja, cada persona contesta desde su celular la lista de verificación de su lugar de trabajo y la Comisión de Seguridad e Higiene la valida. La política se arma contestando preguntas y Humetría te avisa cuando toca la siguiente verificación.',
            'norm' => [
                'title' => 'Qué pide la norma',
                'lead' => 'La NOM-037-STPS-2023, publicada en el DOF el 8 de junio de 2023, fija las condiciones de seguridad y salud en el trabajo para quien trabaja en la modalidad de teletrabajo.',
                'rules' => [
                    ['text' => 'Es teletrabajo cuando la persona trabaja más del 40 % del tiempo en su domicilio o en el lugar que elija. El trabajo ocasional o esporádico no cuenta.', 'source' => 'LFT, art. 330-A; NOM-037-STPS-2023, numeral 5.1 f)'],
                    ['text' => 'Llevar un listado de las personas en teletrabajo con sus datos, sus lugares de trabajo convenidos y el equipo otorgado, y actualizarlo con cada alta y baja.', 'source' => 'NOM-037-STPS-2023, numeral 5.1 y tabla 10.3'],
                    ['text' => 'Tener una Política de Teletrabajo y difundirla a todo el centro, incluidas las personas que no teletrabajan.', 'source' => 'NOM-037-STPS-2023, numeral 5.2 y tabla 10.3'],
                    ['text' => 'Cada lugar de trabajo se revisa con una lista de verificación que valida la Comisión de Seguridad e Higiene. Si el lugar no tiene las condiciones, no se puede teletrabajar ahí, y una segunda versión se aplica periódicamente.', 'source' => 'NOM-037-STPS-2023, numerales 5.4, 5.5 y 5.5.1'],
                    ['text' => 'Entregar el equipo de cómputo y ergonómico, como una silla, y capacitar a las personas al menos una vez al año.', 'source' => 'NOM-037-STPS-2023, numerales 5.7 y 5.9'],
                ],
                'facts' => [
                    ['label' => 'En vigor desde', 'value' => '5 de diciembre de 2023'],
                    ['label' => 'A quién aplica', 'value' => 'Quien teletrabaja más del 40 % de su jornada'],
                    ['label' => 'Evidencias', 'value' => 'Impresas o digitales, al menos un año (num. 10.4)'],
                ],
            ],
            'features' => [
                ['title' => 'El listado de teletrabajo', 'text' => 'Agregas a cada persona según el porcentaje de su jornada, con los 11 datos que pide la norma, y el listado guarda la fecha de cada alta y baja.'],
                ['title' => 'La lista de verificación desde el celular', 'text' => 'Cada persona la contesta por cada lugar de trabajo. Si una respuesta señala un riesgo, se le pide describirlo y, si quiere, una foto. La Comisión valida el lugar, lo valida con un plan de acción o no lo valida.'],
                ['title' => 'La política contestando preguntas', 'text' => 'Ocho preguntas cubren los incisos a) a l) del numeral 5.2; también puedes subir tu PDF. Al publicarla, Humetría la envía a todo el centro y registra quién confirmó haberla leído.'],
                ['title' => 'Verificaciones con aviso', 'text' => 'Eliges cada cuánto se verifica cada lugar: 3, 6, 12 o 24 meses, o un plazo propio. Humetría avisa cuando una validación está por vencer y, al vencer, el lugar vuelve a quedar pendiente.'],
                ['title' => 'El expediente para la inspección', 'text' => 'Un expediente por centro, ordenado como la tabla 10.3, con el equipo entregado y su acuse, las capacitaciones y lo que falta. Se imprime desde el navegador.'],
            ],
            'audience' => [
                'company' => 'RH lleva el listado y la política, la Comisión de Seguridad e Higiene valida los lugares de su centro y cada persona contesta su lista desde el celular.',
                'consultant' => 'Si asesoras a varias empresas, llevas el expediente de teletrabajo de cada una por separado desde la misma cuenta.',
            ],
            'privacy' => 'La lista de verificación lleva nombre, porque la Comisión necesita saber de quién es el lugar. El domicilio, el estado civil, el teléfono y las fotos solo los ven el dueño, RH y la Comisión de su centro, y lo que revela la salud se contesta con consentimiento expreso.',
            'faqs' => [
                ['question' => '¿A quién aplica la NOM-037?', 'answer' => 'A quien trabaja más del 40 % del tiempo en su domicilio o en el lugar que elija, bajo la modalidad de teletrabajo (LFT, art. 330-A). La STPS aclaró que el porcentaje se mide sobre la jornada del contrato. El trabajo ocasional o esporádico no es teletrabajo.'],
                ['question' => '¿Quién decide si un lugar de teletrabajo es apto?', 'answer' => 'La Comisión de Seguridad e Higiene, que valida las condiciones declaradas en la lista de verificación (numerales 4.27 y 5.5). La persona contesta la lista, pero eso no le traslada la responsabilidad ni el costo de adecuar el lugar.'],
                ['question' => '¿Cada cuánto se revisa el lugar de teletrabajo?', 'answer' => 'La norma pide una segunda versión de la lista para aplicarla periódicamente (numeral 5.5.1) y deja al patrón fijar la periodicidad. En Humetría la eliges tú; por defecto son 12 meses.'],
                ['question' => '¿Es obligatorio usar la lista de verificación del Apéndice 3?', 'answer' => 'No. El Apéndice 3 es informativo y el patrón puede agregar, quitar o modificar preguntas. Humetría parte de sus preguntas literales y la Comisión de cada centro puede editarlas.'],
                ['question' => '¿Qué equipo debe dar la empresa para el teletrabajo?', 'answer' => 'Equipo de cómputo, una silla ergonómica u otra apropiada, insumos y aditamentos (numeral 5.7). Es válido presentar el listado del equipo firmado por la persona como recibido, y si no acepta algo, firma la negativa con su motivo.'],
                ['question' => '¿Cada cuánto hay que capacitar a quien teletrabaja?', 'answer' => 'Al menos una vez al año (numeral 5.9), sobre los peligros del lugar de trabajo, la Política de Teletrabajo y el manejo de las tecnologías (numeral 8.1). Humetría registra cada capacitación y avisa antes de que pasen 12 meses.'],
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
