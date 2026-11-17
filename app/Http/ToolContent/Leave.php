<?php

declare(strict_types=1);

namespace App\Http\ToolContent;

use App\Http\ToolPages;

/**
 * The page of vacaciones y permisos (/vacaciones-y-permisos). What it says of the law comes from
 * docs/normas/vacaciones/criterios.md and docs/normas/vacaciones/texto-de-referencia.md.
 *
 * @phpstan-import-type Page from ToolPages
 */
final class Leave
{
    /** @return Page */
    public static function page(): array
    {
        return [
            'title' => 'Días de vacaciones por ley: saldos, solicitudes y permisos',
            'description' => 'Calcula los días de vacaciones por ley de cada persona, 12 desde el primer año, con solicitudes que aprueba su jefe y la constancia anual de la LFT.',
            'name' => 'Vacaciones y permisos',
            'h1' => 'Vacaciones y permisos conforme a la Ley Federal del Trabajo',
            'lead' => 'Cada persona ve su saldo de vacaciones conforme a la ley o a la política de tu empresa, si da más, y lo solicita desde su portal. Su jefe aprueba, el calendario del equipo muestra quién falta y el expediente guarda la solicitud y la constancia anual.',
            'norm' => [
                'title' => 'Qué pide la ley',
                'lead' => 'Desde el 1 de enero de 2023, la reforma a los artículos 76 y 78 de la Ley Federal del Trabajo da al menos 12 días de vacaciones al cumplir el primer año de servicio.',
                'rules' => [
                    ['text' => 'Al cumplir un año de servicio, al menos 12 días laborables de vacaciones pagadas, y 2 más por cada año hasta llegar a 20. A partir del sexto año, 2 días más por cada cinco de servicio.', 'source' => 'LFT, art. 76'],
                    ['text' => 'La persona disfruta al menos 12 días continuos, y puede distribuir el resto como lo requiera.', 'source' => 'LFT, art. 78'],
                    ['text' => 'Las vacaciones no se pueden cambiar por dinero. Si la relación termina antes de cumplir el año, la persona tiene derecho a una remuneración proporcional.', 'source' => 'LFT, art. 79'],
                    ['text' => 'La prima vacacional es de al menos 25 % sobre los salarios del periodo de vacaciones.', 'source' => 'LFT, art. 80'],
                    ['text' => 'Las vacaciones se conceden dentro de los seis meses siguientes al aniversario, y cada año el patrón entrega una constancia con la antigüedad, el periodo que corresponde y la fecha en que debe disfrutarse.', 'source' => 'LFT, art. 81'],
                ],
                'facts' => [
                    ['label' => 'En vigor desde', 'value' => '1 de enero de 2023'],
                    ['label' => 'Primer año de servicio', 'value' => '12 días laborables'],
                    ['label' => 'Prima vacacional', 'value' => 'Al menos 25 % (art. 80)'],
                ],
            ],
            'features' => [
                ['title' => 'El saldo por ley o por tu política', 'text' => 'Cada periodo nace completo en el aniversario, con la tabla del artículo 76. Tu política puede dar más días o más prima, nunca menos que la ley.'],
                ['title' => 'Se solicita desde el portal y aprueba el jefe', 'text' => 'La persona elige sus días en el calendario y solo se descuentan los laborables, sin los días de descanso obligatorio ni tus festivos. Si el jefe no responde en 3 días hábiles, la solicitud pasa a RH.'],
                ['title' => 'Quién falta y cuándo', 'text' => 'El calendario del equipo muestra las ausencias para planear la cobertura, y RH ve los periodos que están por pasar los seis meses del artículo 81.'],
                ['title' => 'Permisos que no consumen saldo', 'text' => 'Paternidad, maternidad, adopción y permisos especiales con o sin goce vienen listos, y puedes agregar los tuyos. Queda quién, cuándo, cuántos días y quién aprobó.'],
                ['title' => 'La evidencia que la empresa debe poder mostrar', 'text' => 'La constancia anual con el acuse de la persona, cada solicitud con quién la aprobó y cada movimiento del saldo. Nada se borra.'],
            ],
            'audience' => [
                'company' => 'RH define las políticas y los festivos, y resuelve lo que un jefe no atiende. Cada jefe aprueba a su gente y cada persona ve su saldo desde su portal.',
                'consultant' => 'Si asesoras a varias empresas, configuras las políticas de vacaciones de cada una por separado desde la misma cuenta.',
            ],
            'privacy' => 'El tipo de permiso, como una maternidad o una adopción, solo lo ven RH y quien aprueba. El calendario del equipo solo dice si la persona está de vacaciones o con permiso.',
            'faqs' => [
                ['question' => '¿Cuántos días de vacaciones me tocan por ley?', 'answer' => 'Al cumplir el primer año, 12 días laborables; 14 al segundo, 16 al tercero, 18 al cuarto y 20 al quinto. Del sexto al décimo año son 22, y a partir de ahí suben 2 días por cada cinco años de servicio (LFT, art. 76).'],
                ['question' => '¿Desde cuándo aplican los 12 días de vacaciones?', 'answer' => 'Desde el 1 de enero de 2023, cuando entró en vigor el decreto que reformó los artículos 76 y 78 de la Ley Federal del Trabajo, publicado en el DOF el 27 de diciembre de 2022.'],
                ['question' => '¿Se pueden dividir las vacaciones?', 'answer' => 'Sí, pero la persona disfruta al menos 12 días continuos, y el resto lo distribuye como lo requiera (LFT, art. 78). Humetría le avisa al pedir si todavía no tiene un bloque de 12 días seguidos, sin impedirle pedir menos.'],
                ['question' => '¿Se pueden pagar las vacaciones en lugar de tomarlas?', 'answer' => 'No: las vacaciones no se compensan con dinero mientras dura la relación. Si termina antes de cumplir el año, la persona tiene derecho a la parte proporcional (LFT, art. 79). Humetría la calcula en días; el importe lo calcula tu nómina.'],
                ['question' => '¿Cuánto es la prima vacacional?', 'answer' => 'Al menos 25 % sobre los salarios del periodo de vacaciones (LFT, art. 80). Tu política puede fijar un porcentaje mayor, nunca uno menor.'],
                ['question' => '¿En qué plazo deben darse las vacaciones?', 'answer' => 'Dentro de los seis meses siguientes a cumplir el año de servicio, y cada año el patrón entrega una constancia con la antigüedad, los días y la fecha en que deben disfrutarse (LFT, art. 81).'],
            ],
            'film' => [
                'name' => 'humetria-leave',
                'title' => 'Vacaciones y permisos en Humetría',
                'description' => 'La persona ve su saldo de vacaciones conforme a la ley y a la política de su empresa, elige sus días en el calendario de su portal y toca Solicitar; su jefe aprueba, la ausencia aparece en el calendario del equipo y el expediente guarda la solicitud y la constancia anual.',
                'duration' => 26,
                'uploaded' => '2026-11-11',
            ],
        ];
    }
}
