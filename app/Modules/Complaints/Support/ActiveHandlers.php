<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;

/**
 * The designations that count: open, and held by someone who still belongs to the company with a role that can handle
 * complaints. A designation of a person who became a viewer stays on record but covers nothing.
 */
final readonly class ActiveHandlers
{
    public function __construct(private Members $members) {}

    /**
     * @return list<array{handler: ComplaintHandler, member: MemberSummary}>
     */
    public function all(): array
    {
        $members = [];

        foreach ($this->members->complaintHandlers() as $member) {
            $members[$member->userId] = $member;
        }

        $active = [];

        foreach (ComplaintHandler::query()->whereNull('ended_at')->orderByRaw('work_center_id IS NOT NULL')->orderBy('designated_at')->orderBy('id')->get() as $handler) {
            if (isset($members[$handler->user_id])) {
                $active[] = ['handler' => $handler, 'member' => $members[$handler->user_id]];
            }
        }

        return $active;
    }
}
