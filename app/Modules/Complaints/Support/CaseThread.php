<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;

/**
 * Appends to the thread of a case: the next position, taken with the case's row held so two entries never share one. The
 * entries of a handler carry the moment (a handler acts as themselves and is audited); those of the person only the day.
 */
final readonly class CaseThread
{
    public function __construct(private CurrentTenant $tenant) {}

    /**
     * @param  array<string, mixed>|null  $meta  States and user ids; never words.
     */
    public function append(Complaint $complaint, EntryKind $kind, ?int $authorId, ?string $body = null, ?array $meta = null): ComplaintEntry
    {
        // Holding the case's row, the position read next is the last one.
        Complaint::query()->whereKey($complaint->id)->lockForUpdate()->first(['id']);
        // Entries are never deleted while a case lives, so the count is the last position.
        $position = ComplaintEntry::query()->where('complaint_id', $complaint->id)->count() + 1;
        $byReporter = $kind === EntryKind::ReporterMessage;

        return ComplaintEntry::query()->create([
            'tenant_id' => $this->tenant->id(),
            'complaint_id' => $complaint->id,
            'position' => $position,
            'kind' => $kind->value,
            'author_id' => $byReporter ? null : $authorId,
            'body' => $body,
            'meta' => $meta,
            'occurred_at' => $byReporter ? null : now(),
            'occurred_on' => $byReporter ? Dates::today() : null,
        ]);
    }
}
