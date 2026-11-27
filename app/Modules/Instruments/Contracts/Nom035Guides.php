<?php

declare(strict_types=1);

namespace App\Modules\Instruments\Contracts;

/**
 * Which NOM-035 guides a work center applies, by its number of workers (num. 4.4 and 2).
 */
interface Nom035Guides
{
    /**
     * `$workCenterId`, when the caller knows it: in the demo session's center, Guías I and II apply with any number of
     * people up to 50 (the demo is no company); everywhere else it changes nothing.
     *
     * @return list<'I'|'II'|'III'> Guía I always (with anyone at all), plus II from 16 workers or III above 50.
     */
    public function for(int $workers, ?string $workCenterId = null): array;

    /**
     * The same, as instrument keys (nom035.guide-i, nom035.guide-ii, nom035.guide-iii).
     *
     * @return list<string>
     */
    public function instruments(int $workers, ?string $workCenterId = null): array;
}
