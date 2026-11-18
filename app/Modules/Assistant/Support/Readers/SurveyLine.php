<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support\Readers;

use App\Modules\Assistant\Data\ComplianceStatus;
use App\Modules\Engagement\Data\SurveyStanding;
use Carbon\CarbonImmutable;

/**
 * The line of a climate or pulse survey: the one open, with until when and how many of the whole company answered (as RH
 * sees it while it runs), or the draft that waits; and whether the last closed one shows its results. Never an answer, a
 * comment, a score nor a group.
 */
final class SurveyLine
{
    public static function of(string $key, string $label, string $kind, SurveyStanding $standing, string $link): ComplianceStatus
    {
        $current = match (true) {
            $standing->closesOn !== null => "La encuesta de {$kind} está abierta hasta el ".self::day($standing->closesOn).': '.trans_choice('{1} ha contestado|[0,*] han contestado', $standing->answered)." {$standing->answered} de {$standing->invited} personas.",
            $standing->draft => "Tienes una encuesta de {$kind} en borrador, sin lanzar.",
            default => null,
        };
        $last = match (true) {
            $standing->lastClosedOn === null => null,
            $standing->lastResultsShown => ($current === null ? "La última encuesta de {$kind}" : 'La última').' cerró el '.self::day($standing->lastClosedOn).' y sus resultados están listos.',
            default => ($current === null ? "La última encuesta de {$kind}" : 'La última').' cerró el '.self::day($standing->lastClosedOn).' con menos de '.config()->integer('results.min_group').' respuestas: sus resultados no se muestran.',
        };

        [$state, $stateLabel] = match (true) {
            $standing->closesOn !== null => ['in_progress', 'En curso'],
            $standing->lastClosedOn !== null => ['up_to_date', 'Al día'],
            default => ['pending', 'Pendiente'],
        };

        $summary = implode(' ', array_filter([$current, $last])) ?: "Aún no has lanzado ninguna encuesta de {$kind}.";

        return new ComplianceStatus($key, $label, $state, $stateLabel, $summary, $link);
    }

    private static function day(string $date): string
    {
        return CarbonImmutable::parse($date)->isoFormat('D [de] MMMM');
    }
}
