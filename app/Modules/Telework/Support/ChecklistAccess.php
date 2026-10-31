<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Who may reach the checklist of a work center. RH and the center's Comisión read it; only an assigned Comisión member
 * edits it. Someone who can neither manage Telework nor sit on that Comisión gets a 404; RH not on it, a 403. The
 * FormRequests ask it first, so whoever may not edit is told so whatever they send.
 */
final readonly class ChecklistAccess
{
    public function __construct(private WorkCenters $workCenters, private CommitteeGate $gate) {}

    public function visible(int $userId, mixed $id): WorkCenterSummary
    {
        Gate::authorize('view-telework');
        $center = is_string($id) && Str::isUuid($id) ? $this->workCenters->findActive($id) : null;
        abort_if($center === null, 404);
        abort_unless(Gate::allows('manage-telework') || $this->gate->isMember($userId, $center->id), 404);

        return $center;
    }

    public function editable(int $userId, mixed $id): WorkCenterSummary
    {
        $center = $this->visible($userId, $id);
        abort_unless($this->gate->isMember($userId, $center->id), 403);

        return $center;
    }
}
