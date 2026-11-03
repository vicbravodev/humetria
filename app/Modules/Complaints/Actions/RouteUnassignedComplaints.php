<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Assigns again the open complaints nobody holds (their handlers left), by the same rules as at receipt: the conflict still
 * keeps a complaint from the role it is against, even when the person who asks is that role. Whoever asks never reads the
 * cases nor chooses who gets them, so asking leaks nothing. Complaints somebody holds are not touched: a handler designated
 * later does not take what came before.
 */
final readonly class RouteUnassignedComplaints
{
    public function __construct(private AssignComplaint $assign) {}

    /**
     * @return int how many assignments were made.
     */
    public function handle(int $byUserId): int
    {
        $made = DB::transaction(function () use ($byUserId): int {
            $made = 0;

            foreach (Complaint::query()->where('status', '<>', ComplaintStatus::Closed->value)->whereNotIn('id', ComplaintAssignment::query()->whereNull('ended_on')->select('complaint_id'))->get() as $complaint) {
                $made += $this->assign->handle($complaint, $byUserId);
            }

            return $made;
        });

        Log::info('complaints.assignment_changed', ['reason' => 'unassigned_routed', 'count' => $made]);

        return $made;
    }
}
