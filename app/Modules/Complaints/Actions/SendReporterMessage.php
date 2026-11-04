<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Actions;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Support\CaseThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * A message of the person, from their follow-up. It keeps the day and not the hour; the log line names the channel and no
 * case, since a case id next to the moment of the line would tie the message to the person's visit (criterio C-46).
 */
final readonly class SendReporterMessage
{
    public function __construct(private CaseThread $thread) {}

    /**
     * @throws ValidationException
     */
    public function handle(Complaint $complaint, string $body): void
    {
        if ($complaint->status === ComplaintStatus::Closed) {
            throw ValidationException::withMessages(['body' => 'Este caso está cerrado: ya no se puede escribir en él.']);
        }

        DB::transaction(fn () => $this->thread->append($complaint, EntryKind::ReporterMessage, null, $body));

        Log::info('complaints.reporter_message_sent', ['channel_id' => $complaint->channel_id]);
    }
}
