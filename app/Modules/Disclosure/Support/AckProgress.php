<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where the confirmation of a version stands (criterio C-53): the share is worked out over the people who are active today in
 * the centers the version reached, so someone who left drops out of it and their row stays. Counts and the ids of who is
 * pending or confirmed; the names are the roster's, read a page at a time by whoever lists them.
 */
final readonly class AckProgress
{
    private const int CHUNK = 1000;

    public function __construct(private Roster $roster) {}

    /**
     * @return array{active: int, confirmed: int, pending: int, percent: int}
     */
    public function of(Document $document): array
    {
        $active = $this->active($document);
        $confirmed = $this->count($document, $active, 'confirmed');

        return [
            'active' => count($active),
            'confirmed' => $confirmed,
            'pending' => count($active) - $confirmed,
            'percent' => $active === [] ? 0 : intdiv($confirmed * 100, count($active)),
        ];
    }

    /**
     * How many people each view of the list holds.
     *
     * @return array{all: int, pending: int, confirmed: int}
     */
    public function counts(Document $document): array
    {
        $active = $this->active($document);
        $all = $this->count($document, $active, 'all');
        $confirmed = $this->count($document, $active, 'confirmed');

        return ['all' => $all, 'pending' => $all - $confirmed, 'confirmed' => $confirmed];
    }

    /**
     * The ids of the active people with a request for the version, narrowed to those pending or confirmed.
     *
     * @param  'all'|'pending'|'confirmed'  $status
     * @return list<string>
     */
    public function requested(Document $document, string $status): array
    {
        $ids = [];

        foreach (array_chunk($this->active($document), self::CHUNK) as $chunk) {
            $found = $this->narrow($this->requests($document, $chunk), $status)->get(['employee_id'])->map(fn (Acknowledgment $ack): string => $ack->employee_id)->all();
            $ids = [...$ids, ...$found];
        }

        return $ids;
    }

    /**
     * The pending people whose name, email or number matches what was typed: what «all the pending ones I found» means.
     *
     * @return list<string>
     */
    public function pendingMatching(Document $document, string $search): array
    {
        return $this->roster->matchingIds(new RosterQuery(employeeIds: $this->requested($document, 'pending'), search: $search));
    }

    /**
     * @return list<string>
     */
    private function active(Document $document): array
    {
        $centers = array_values(DocumentCenter::query()->where('document_id', $document->id)->get(['work_center_id'])->map(fn (DocumentCenter $reach): string => $reach->work_center_id)->all());

        return array_values(array_unique(array_merge([], ...array_values($this->roster->activeIdsInMany($centers)))));
    }

    /**
     * @param  list<string>  $active
     * @param  'all'|'pending'|'confirmed'  $status
     */
    private function count(Document $document, array $active, string $status): int
    {
        return array_sum(array_map(fn (array $chunk): int => $this->narrow($this->requests($document, $chunk), $status)->count(), array_chunk($active, self::CHUNK)));
    }

    /**
     * @param  list<string>  $employees
     * @return Builder<Acknowledgment>
     */
    private function requests(Document $document, array $employees): Builder
    {
        return Acknowledgment::query()->where('document_id', $document->id)->whereIn('employee_id', $employees);
    }

    /**
     * @param  Builder<Acknowledgment>  $query
     * @param  'all'|'pending'|'confirmed'  $status
     * @return Builder<Acknowledgment>
     */
    private function narrow(Builder $query, string $status): Builder
    {
        return match ($status) {
            'pending' => $query->whereNull('acknowledged_at'),
            'confirmed' => $query->whereNotNull('acknowledged_at'),
            'all' => $query,
        };
    }
}
