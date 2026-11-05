<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\DisseminationRecord;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What the page of the disclosure receives (criterio C-53): the version it follows, how many people confirmed it, and the
 * people by name with what is recorded of each, 15 at a time. It is for whoever manages the disclosure: the names of who has
 * not confirmed are not for the viewer, so the viewer does not get this page. No email, no link, no address.
 */
final readonly class DisseminationPages
{
    private const int PER_PAGE = 15;

    public function __construct(private AckProgress $progress, private Roster $roster, private WorkCenters $workCenters, private PolicyStanding $policies, private DeliveryStanding $delivery, private ResultsStanding $results, private Companies $companies) {}

    /**
     * @param  'all'|'pending'|'confirmed'  $status
     * @return array<string, mixed>
     */
    public function show(?string $versionId, string $status, string $search): array
    {
        $records = $this->records();
        $version = $versionId === null ? $this->published()->orderByDesc('version')->first() : $this->published()->findOrFail($versionId);

        if ($version === null) {
            return ['version' => null, 'versions' => [], 'progress' => null, 'counts' => null, 'filters' => ['status' => $status, 'q' => $search], 'people' => $this->roster->page(new RosterQuery(employeeIds: []), self::PER_PAGE), 'today' => Days::today(), 'publishedDay' => null, 'records' => $records, 'centers' => $this->centers()];
        }

        $people = $this->roster->page(new RosterQuery(employeeIds: $this->progress->requested($version, $status), search: $search), self::PER_PAGE);
        $requests = Acknowledgment::query()->where('document_id', $version->id)->whereIn('employee_id', array_map(fn (RosterEntry $person): string => $person->id, $people->items()))->get()->keyBy('employee_id');

        return [
            'version' => ['id' => $version->id, 'version' => $version->version, 'publishedOn' => $version->published_at === null ? null : Days::moment($version->published_at), 'centers' => $version->centers()->count()],
            'versions' => array_values($this->published()->orderByDesc('version')->limit(10)->get(['id', 'version', 'published_at'])->map(fn (Document $document): array => ['id' => $document->id, 'version' => $document->version, 'publishedOn' => $document->published_at === null ? null : Days::moment($document->published_at)])->all()),
            'progress' => $this->progress->of($version),
            'counts' => $this->progress->counts($version),
            'filters' => ['status' => $status, 'q' => $search],
            'people' => $people->through(function (RosterEntry $person) use ($requests): array {
                $ack = $requests->get($person->id);

                return [
                    'id' => $person->id,
                    'name' => $person->name,
                    'jobTitle' => $person->jobTitle,
                    'state' => $ack?->acknowledged_at === null ? 'pending' : 'confirmed',
                    'channelLabel' => match ($ack?->channel) {
                        'portal' => 'Desde su enlace',
                        'manual' => 'En papel',
                        default => null,
                    },
                    'on' => $ack?->acknowledged_at === null ? null : Days::moment($ack->acknowledged_at),
                    'hasEmail' => $person->email !== null,
                ];
            }),
            'today' => Days::today(),
            'publishedDay' => $version->published_at?->setTimezone(config()->string('app.display_timezone'))->toDateString(),
            'records' => $records,
            'centers' => $this->centers(),
        ];
    }

    /**
     * The centers in use a page at a time (board V4-Difusion-Registro): how many of their people confirmed the policy in force
     * there (null without one; the acknowledgment is of reading the policy, never a survey answer) and where the results for
     * their people stand (criterio C-54): ready to publish, published, waiting for a closed evaluation or not applicable.
     *
     * @return LengthAwarePaginator<int, array{id: string, name: string, acks: array{confirmed: int, active: int}|null, results: array{state: string, closedOn: string|null, campaignId: string|null}}>
     */
    private function centers(): LengthAwarePaginator
    {
        $page = $this->workCenters->activePage(config()->integer('disclosure.per_page'), 'centers');
        $ids = array_values(array_map(fn (WorkCenterSummary $center): string => $center->id, $page->items()));
        $acks = $this->delivery->acknowledgments($this->policies->inForce($ids));
        $results = $this->results->forCenters($ids);

        return $page->withQueryString()->through(function (WorkCenterSummary $center) use ($acks, $results): array {
            $fact = $results[$center->id] ?? null;
            $state = Matrix::resultsState($fact);
            $closed = in_array($state, ['ready', 'done'], true) ? $fact?->latest : null;

            return [
                'id' => $center->id,
                'name' => $center->name,
                'acks' => isset($acks[$center->id]) ? ['confirmed' => $acks[$center->id]['confirmed'], 'active' => $acks[$center->id]['active']] : null,
                'results' => ['state' => $state, 'closedOn' => $closed?->closedOn, 'campaignId' => $closed?->id],
            ];
        });
    }

    /**
     * @return Builder<Document>
     */
    private function published(): Builder
    {
        return Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published);
    }

    /**
     * The physical means recorded, the newest first, five at a time.
     *
     * @return LengthAwarePaginator<int, array{id: string, workCenter: string, mediumLabel: string, items: list<string>, doneOn: string, note: string|null, signatures: int|null, evidence: string|null}>
     */
    private function records(): LengthAwarePaginator
    {
        $names = [];
        $tenantSlug = $this->companies->current()->slug;

        foreach ($this->workCenters->all() as $center) {
            $names[$center->id] = $center->name;
        }

        return DisseminationRecord::query()->orderByDesc('done_on')->orderByDesc('id')->paginate(config()->integer('disclosure.records_per_page'), pageName: 'records')->withQueryString()
            ->through(fn (DisseminationRecord $record): array => [
                'id' => $record->id,
                'workCenter' => $names[$record->work_center_id] ?? '',
                'mediumLabel' => $record->medium->label(),
                'items' => $record->covered_items,
                'doneOn' => Days::calendar($record->done_on),
                'note' => $record->note,
                'signatures' => $record->signatures,
                'evidence' => $record->evidence_path === null ? null : route('tenant.disclosure.dissemination.records.evidence', [$tenantSlug, $record->id]),
            ]);
    }
}
