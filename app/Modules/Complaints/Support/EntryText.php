<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintResolution;
use App\Modules\Complaints\Enums\EntryKind;
use App\Modules\Complaints\Models\ComplaintEntry;

/** What an entry says on the page: its own words, or, for the case's history, a sentence made of its states. */
final readonly class EntryText
{
    /**
     * @param  array<int, string>  $names  The people of the company, by user id.
     */
    public function of(ComplaintEntry $entry, array $names): ?string
    {
        return match (EntryKind::from($entry->kind)) {
            EntryKind::ReporterMessage, EntryKind::HandlerMessage, EntryKind::InternalNote => $entry->body,
            EntryKind::StatusChange => $this->statusChange($entry->meta ?? []),
            EntryKind::AssignmentChange => 'compartió el caso con '.$this->nameIn($entry->meta, $names).'.',
            EntryKind::Recusal => 'se excusó del caso por conflicto de interés.',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function statusChange(array $meta): string
    {
        if (($meta['acknowledged'] ?? false) === true) {
            return 'acusó recibo.';
        }

        $resolution = is_string($meta['resolution'] ?? null) ? ComplaintResolution::tryFrom($meta['resolution']) : null;

        return match (true) {
            ($meta['to'] ?? null) === 'closed' => 'cerró el caso'.($resolution === null ? '.' : ": {$resolution->label()}."),
            ($meta['from'] ?? null) === 'closed' => 'abrió el caso de nuevo.',
            default => 'pasó el caso a revisión.',
        };
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @param  array<int, string>  $names
     */
    private function nameIn(?array $meta, array $names): string
    {
        $id = $meta['user_id'] ?? null;

        return is_int($id) ? ($names[$id] ?? 'otra persona') : 'otra persona';
    }
}
