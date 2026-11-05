<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Contracts;

/**
 * Where the Programa de intervención of each evaluation of the current company stands, as the panel reads it for the
 * NOM-035 route: none (no actions yet), in_progress, overdue or done. An evaluation without a Programa needed none (one is
 * drafted when it closes with something to attend) or has not closed.
 */
interface ProgramStandings
{
    /**
     * @param  list<string>  $campaignIds
     * @return array<string, string> by campaign id; one without a Programa is left out
     */
    public function forEvaluations(array $campaignIds): array;
}
