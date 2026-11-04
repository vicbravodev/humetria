<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Data;

use App\Modules\Interventions\Enums\ActionKind;
use App\Modules\Interventions\Enums\ActionLevel;
use App\Modules\Interventions\Enums\ActionSubjects;
use App\Modules\Interventions\Enums\Professional;

/**
 * What HR fills in for an action of the Programa (num. 8.4 and 8.5): what, at which level, for whom (the center or areas),
 * when and who answers for it. `id` is null for a new action, which also needs `itemId`; an existing one keeps its row.
 */
final readonly class ActionData
{
    /**
     * @param  list<string>  $areaIds  only read when the action is for areas
     */
    public function __construct(
        public ?string $id,
        public ?string $itemId,
        public ActionKind $kind,
        public ActionLevel $level,
        public string $title,
        public string $description,
        public ActionSubjects $subjects,
        public array $areaIds,
        public ?string $startsOn,
        public string $dueOn,
        public ?string $responsibleName,
        public ?int $responsibleUserId,
        public ?Professional $professional,
    ) {}
}
