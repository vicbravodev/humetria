<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Results\Data\RiskBreakdown;
use App\Modules\Results\Data\RiskGroup;

/**
 * «Lo que más atención necesita» (boards V2-N035-Compartir and V4-Pagina-Personas): the domains with the most questionnaires
 * in Alto or Muy alto, the most first, three at most; one with none is left out. Only of figures Results could show: the whole
 * center, above the minimum (criterio C-54).
 */
final class ResultThemes
{
    private const int THEMES = 3;

    /** @return list<string> */
    public static function of(RiskBreakdown $breakdown): array
    {
        $high = fn (RiskGroup $group): int => ($group->levels[3] ?? 0) + ($group->levels[4] ?? 0);
        $domains = array_values(array_filter($breakdown->domains, fn (RiskGroup $group): bool => $high($group) > 0));
        usort($domains, fn (RiskGroup $a, RiskGroup $b): int => $high($b) <=> $high($a));

        return array_map(fn (RiskGroup $group): string => $group->label, array_slice($domains, 0, self::THEMES));
    }
}
