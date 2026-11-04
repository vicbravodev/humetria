<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\CaseThread;
use App\Modules\Complaints\Support\Dates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** The person withdraws their complaint: it closes with that resolution, keeping the day and no hour; a handler may open it again. */
final readonly class WithdrawComplaint
{
    public const string TEXT = 'La persona retiró el caso.';

    public function __construct(private CaseThread $thread) {}

    /**
     * @throws ValidationException
     */
    public function handle(Complaint $complaint): void
    {
        if ($complaint->status === ComplaintStatus::Closed) {
            throw ValidationException::withMessages(['reference' => 'Este caso ya está cerrado.']);
        }

        DB::transaction(function () use ($complaint): void {
            $complaint->update(['status' => ComplaintStatus::Closed, 'resolution' => ComplaintResolution::Withdrawn, 'closed_on' => Dates::today(), 'closed_at' => null]);
            $this->thread->append($complaint, EntryKind::ReporterMessage, null, self::TEXT);
        });

        Log::info('complaints.withdrawn', ['channel_id' => $complaint->channel_id]);
    }
}
