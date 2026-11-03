<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

/**
 * The model of the written procedure a company starts from. It is Humetría's draft, not legal advice: the company edits it
 * to what it really does, and a lawyer should read it before the channel is opened (see the design's «Pendiente»).
 */
final class ProcedureModel
{
    public static function text(): string
    {
        return <<<'TEXT'
            1. Cualquier persona que trabaja en la empresa puede presentar una queja por prácticas opuestas a un entorno organizacional favorable, una denuncia de violencia laboral o un aviso de acontecimiento traumático severo, desde el enlace o el cartel del canal.
            2. Puede hacerlo de forma anónima o confidencial. En ambos casos recibe una referencia y una clave para dar seguimiento; si pierde la clave no puede recuperarse.
            3. Solo la reciben las personas encargadas que la empresa designó. Si la queja es contra recursos humanos o contra la dirección, no la recibe quien esté involucrada.
            4. La persona encargada acusa recibo dentro del plazo de este procedimiento, revisa los hechos con discreción, puede pedir más datos a quien presentó la queja y deja constancia de lo que hace.
            5. La empresa resuelve y comunica una respuesta general dentro del plazo de este procedimiento. La plataforma registra el trámite; no determina si hubo o no violencia laboral.
            6. Nadie debe tomar represalias contra quien presenta una queja de buena fe.
            TEXT;
    }
}
