<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Support\Nom035;

use App\Modules\Instruments\Data\GateDefinition;
use App\Modules\Instruments\Data\ItemDefinition;

/**
 * What the three NOM-035 guides share: the edition of the norm they transcribe and the two yes/no gates of Guías II
 * and III, worded as the DOF words them.
 */
final class Nom035
{
    /** The DOF edition the guides are transcribed from. Answers are always scored with the version they were given in. */
    public const string VERSION = 'dof-2018-10-23';

    /**
     * @param  array<int, string>  $texts  by item number
     * @return list<ItemDefinition>
     */
    public static function items(array $texts): array
    {
        $items = [];

        foreach ($texts as $number => $text) {
            $items[] = new ItemDefinition($number, $text);
        }

        return $items;
    }

    public static function clientsGate(): GateDefinition
    {
        return new GateDefinition(
            'serves_clients',
            'En mi trabajo debo brindar servicio a clientes o usuarios:',
            'Si su respuesta fue "SÍ", responda las preguntas siguientes. Si su respuesta fue "NO" pase a las preguntas de la sección siguiente.',
        );
    }

    public static function supervisionGate(): GateDefinition
    {
        return new GateDefinition(
            'supervises',
            'Soy jefe de otros trabajadores:',
            'Si su respuesta fue "SÍ", responda las preguntas siguientes. Si su respuesta fue "NO", ha concluido el cuestionario.',
        );
    }
}
