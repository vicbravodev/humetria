<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the page of the disclosure receives (criterio C-53): the version it follows, how many people confirmed it, and the
 * people by name with what is recorded of each, 15 at a time. It is for whoever manages the disclosure: the names of who has
 * not confirmed are not for the viewer, so the viewer does not get this page. No email, no link, no address.
 */
final readonly class DisseminationPages
{
    private const int PER_PAGE = 15;

    public function __construct(private AckProgress $progress, private Roster $roster) {}

    /**
     * @param  'all'|'pending'|'confirmed'  $status
     * @return array<string, mixed>
     */
    public function show(?string $versionId, string $status, string $search): array
    {
        $version = $versionId === null ? $this->published()->orderByDesc('version')->first() : $this->published()->findOrFail($versionId);

        if ($version === null) {
            return ['version' => null, 'versions' => [], 'progress' => null, 'counts' => null, 'filters' => ['status' => $status, 'q' => $search], 'people' => $this->roster->page(new RosterQuery(employeeIds: []), self::PER_PAGE), 'today' => Days::today(), 'publishedDay' => null];
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
        ];
    }

    /**
     * @return Builder<Document>
     */
    private function published(): Builder
    {
        return Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published);
    }
}
