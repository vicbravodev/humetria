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
     * The policy in force of each of those work centers, as the dossier prints it: its version, the day it was published
     * (display time zone, «3 nov 2026») and how many of the active people confirmed they read it.
     *
     * @param  list<string>  $workCenterIds
     * @return array<string, array{version: int, publishedOn: string, confirmed: int, expected: int}> by work center id; one without policy is left out
     */
    public function policySummaries(array $workCenterIds): array;

    /**
     * The day each of those evaluations had its results published (display time zone, «3 nov 2026»); one not published is left out.
     *
     * @param  list<string>  $campaignIds
     * @return array<string, string> by campaign id
     */
    public function publishedOn(array $campaignIds): array;

    /**
     * Of those evaluations, the ones whose results are published to the people and not withdrawn.
     *
     * @param  list<string>  $campaignIds
     * @return list<string> in the order given
     */
    public function sharedEvaluations(array $campaignIds): array;

    /**
     * Of those evaluations, the ones whose results are published and not withdrawn, apart by whether the people can reach them
     * (`shared`) or the company still has to turn on their page or record a physical means (`unseen`).
     *
     * @param  list<string>  $campaignIds
     * @return array{shared: list<string>, unseen: list<string>} each in the order given
     */
    public function sharing(array $campaignIds): array;
}
