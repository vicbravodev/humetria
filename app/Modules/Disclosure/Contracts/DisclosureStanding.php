<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Contracts;

/**
 * What the current company has made known to its people, as the panel reads it for the NOM-035 route: where the policy of
 * prevention is in force (5.1) and which evaluations have their results available to the people (7.8).
 */
interface DisclosureStanding
{
    /**
     * Of those work centers, the ones a published version of the policy reached.
     *
     * @param  list<string>  $workCenterIds
     * @return list<string> in the order given
     */
    public function withPolicy(array $workCenterIds): array;

    /**
     * Of those evaluations, the ones whose results are published to the people and not withdrawn.
     *
     * @param  list<string>  $campaignIds
     * @return list<string> in the order given
     */
    public function sharedEvaluations(array $campaignIds): array;
}
