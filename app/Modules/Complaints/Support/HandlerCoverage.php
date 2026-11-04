<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\RespondentKind;

/**
 * Who would receive a complaint of each kind: the active handlers whose scope includes the center, leaving out the role the
 * complaint is against. A kind nobody would receive is not accepted by the form (criterio C-45): the company would
 * be handing the complaint to the person it is about.
 */
final readonly class HandlerCoverage
{
    public function __construct(private ActiveHandlers $active) {}

    /**
     * @return list<int> the user ids, in the order of the designation.
     */
    public function handlerIds(RespondentKind $kind, ?string $workCenterId): array
    {
        $ids = [];

        foreach ($this->active->all() as ['handler' => $handler, 'member' => $member]) {
            $inScope = $handler->work_center_id === null || $handler->work_center_id === $workCenterId;

            if ($inScope && $member->role->value !== $kind->excludedRole()) {
                $ids[$member->userId] = $member->userId;
            }
        }

        return array_values($ids);
    }

    /**
     * How many people would receive each kind when the person names no work center: the handlers of the whole company.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = [];

        foreach (RespondentKind::cases() as $kind) {
            $counts[$kind->value] = count($this->handlerIds($kind, null));
        }

        return $counts;
    }
}
