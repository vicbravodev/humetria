<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\CaseThread;
use Illuminate\Support\Facades\Log;

/** An internal note: only the people who have the case read it; the person never does. */
final readonly class AddInternalNote
{
    public function __construct(private CaseThread $thread) {}

    public function handle(Complaint $complaint, string $body, int $by): void
    {
        $this->thread->append($complaint, EntryKind::InternalNote, $by, $body);

        Log::info('complaints.note_added', ['complaint_id' => $complaint->id]);
    }
}
