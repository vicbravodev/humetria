<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\WorkerNotice;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Who may review a notice (criterio C-26): RH, or the assigned Comisión of the work center of the person who wrote it.
 * Anyone else (another center's Comisión, a viewer) is told it does not exist.
 */
final readonly class NoticeAccess
{
    public function __construct(private CommitteeGate $gate) {}

    public function canReview(int $userId, string $workCenterId): bool
    {
        return Gate::allows('manage-telework') || $this->gate->isMember($userId, $workCenterId);
    }

    /** The notice in the address, or a 404 when it is not of this company or the user may not review it. */
    public function reviewable(int $userId, mixed $id): WorkerNotice
    {
        $notice = is_string($id) && Str::isUuid($id) ? WorkerNotice::query()->find($id) : null;
        abort_if($notice === null, 404);
        $teleworker = Teleworker::query()->findOrFail($notice->teleworker_id, ['id', 'work_center_id']);
        abort_unless($this->canReview($userId, $teleworker->work_center_id), 404);

        return $notice;
    }
}
