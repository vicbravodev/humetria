<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Results\Contracts\ProgramOutlines;
use App\Modules\Results\Data\ProgramOutlineAction;
use App\Modules\Results\Data\ProgramOutlineItem;

/**
 * The measures of the Programa de intervención of an evaluation that the public page may show (criterio C-52), read through
 * Results' port and copied field by field onto a DTO of Disclosure: what reaches a page that anybody with a link reads is decided
 * here and in no other module. Only what is for the whole center and of the first or second level (organizational and
 * group) and was not cancelled: an action for areas names who is in them, and the third level (clinical care) is for people. The
 * clinical, the responsible, the description, the analysis, the files and the follow-up never leave. The Programa has no flag of
 * its own «visible to the people»; the choice is RH's, made when it publishes the results (ResultPublication::show_measures).
 */
final readonly class ProgramMeasureFeed
{
    private const array VIOLENCE_KINDS = ['violence_prevention'];

    private const array LEVELS = [1, 2];

    public function __construct(private ProgramOutlines $programs) {}

    /**
     * @return list<PublicMeasure>
     */
    public function forCampaign(string $campaignId): array
    {
        $outline = $this->programs->forCampaign($campaignId);
        $measures = [];

        foreach ($outline->items ?? [] as $item) {
            foreach ($this->actions($item) as $action) {
                $measures[] = new PublicMeasure(
                    $action->title,
                    in_array($action->kindKey, self::VIOLENCE_KINDS, true) ? 'b' : 'c',
                    $action->levelValue,
                    $action->statusKey,
                    $action->status,
                    $action->dueOn,
                );
            }
        }

        return $measures;
    }

    /**
     * @return list<ProgramOutlineAction>
     */
    private function actions(ProgramOutlineItem $item): array
    {
        return array_values(array_filter($item->actions, fn (ProgramOutlineAction $action): bool => $action->wholeCenter && in_array($action->levelValue, self::LEVELS, true) && $action->statusKey !== 'cancelled'));
    }
}
