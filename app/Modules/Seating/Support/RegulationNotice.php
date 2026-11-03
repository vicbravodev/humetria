<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

/**
 * The notice that goes with every draft of the regulation, on the page and when printed. It cannot be removed: the model
 * clauses are Humetría's, not legal advice (criterio C-13).
 */
final class RegulationNotice
{
    public const string TEXT = 'Este borrador es un modelo de Humetría: no es asesoría legal. Lo revisa su abogado y lo aprueba la comisión mixta antes de firmarlo, depositarlo y repartirlo. Humetría no deposita ni presenta nada ante ninguna autoridad.';
}
