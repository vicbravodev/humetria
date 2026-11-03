<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintEntry;

/**
 * What a person reads when they follow their complaint up: where it stands in general words, how many people attend it (never
 * who), and the messages between them and the handlers. Notes and the case's history are not theirs to read, nor the names
 * of the handlers, nor what the complaint says.
 */
final readonly class FollowUpPages
{
    private const int PER_PAGE = 20;

    /**
     * @return array<string, mixed>
     */
    public function view(Complaint $complaint): array
    {
        $closed = $complaint->status === ComplaintStatus::Closed;

        return [
            'status' => $complaint->status === ComplaintStatus::New ? 'received' : $complaint->status->value,
            'statusLabel' => $complaint->status->reporterLabel(),
            'resolution' => $complaint->resolution?->reporterLabel(),
            'handlers' => ComplaintAssignment::query()->where('complaint_id', $complaint->id)->whereNull('ended_on')->count(),
            'canReply' => ! $closed,
            'canWithdraw' => ! $closed,
            'entries' => $this->entries($complaint),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function entries(Complaint $complaint): array
    {
        $page = ComplaintEntry::query()
            ->where('complaint_id', $complaint->id)
            ->whereIn('kind', [EntryKind::ReporterMessage->value, EntryKind::HandlerMessage->value])
            ->orderByDesc('position')
            ->paginate(self::PER_PAGE);

        return [
            'data' => array_map(fn (ComplaintEntry $entry): array => [
                'from' => $entry->kind === EntryKind::ReporterMessage->value ? 'reporter' : 'handlers',
                'text' => $entry->body,
                'day' => $entry->occurred_on?->toDateString() ?? $entry->occurred_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            ], $page->items()),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ];
    }
}
