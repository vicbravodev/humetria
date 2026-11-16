<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use App\Modules\WorkerAccess\Contracts\WorkerCredentials;
use App\Modules\WorkerAccess\Data\CredentialSummary;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;

/**
 * What RH's page «Accesos» sends to the browser: the people active in the centers in use (or in one), how many have a key,
 * a link RH issued that still opens, or neither; then the people of one tab, 25 at a time, searched by name, each with
 * the link RH issued them and their keys (the active first): their name, when registered and last used, and whether the
 * key is synced. Never a credential id, a public key, a token or the person's email (only whether they have one).
 */
final readonly class AccessPages
{
    private const int PER_PAGE = 25;

    /** with_key: a key in use; invited: no key in use and a link RH issued that still opens; not_invited: the rest. */
    private const array VIEWS = ['all', 'with_key', 'invited', 'not_invited'];

    public function __construct(private Roster $roster, private WorkCenters $workCenters, private WorkerCredentials $credentials) {}

    /**
     * @return array<string, mixed>
     */
    public function index(?WorkCenterSummary $center, string $view, string $search): array
    {
        [$names, $people] = $this->scope($center);
        $links = WorkerAccessLink::issuedWaiting($people);
        $states = $this->states($people, $links);
        $view = in_array($view, self::VIEWS, true) ? $view : 'all';
        $page = $this->roster->page(new RosterQuery(
            employeeIds: $view === 'all' ? $people : array_keys($states, $view, true),
            search: $search,
        ), self::PER_PAGE)->withQueryString();
        $keys = $this->keysOf(array_values(array_map(fn (RosterEntry $person): string => $person->id, $page->items())));

        return [
            'center' => $center === null ? null : ['id' => $center->id, 'name' => $center->name],
            'filters' => ['q' => $search, 'status' => $view],
            'counts' => [
                'all' => count($states),
                ...array_combine(array_slice(self::VIEWS, 1), array_map(fn (string $state): int => count(array_keys($states, $state, true)), array_slice(self::VIEWS, 1))),
            ],
            'people' => $page->through(fn (RosterEntry $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'jobTitle' => $person->jobTitle,
                'center' => $names[$person->workCenterId] ?? '—',
                'hasEmail' => $person->email !== null,
                'status' => $states[$person->id] ?? 'not_invited',
                'link' => isset($links[$person->id]) ? self::link($links[$person->id]) : null,
                'keys' => $keys[$person->id] ?? [],
            ]),
            'canManage' => Gate::allows('manage-worker-access'),
            'linkHours' => config()->integer('worker_access.links.issued_hours'),
        ];
    }

    /**
     * The name of each center in use, and the people active in them (or in `$center`). Two queries.
     *
     * @return array{0: array<string, string>, 1: list<string>}
     */
    private function scope(?WorkCenterSummary $center): array
    {
        $names = [];
        foreach ($this->workCenters->all() as $summary) {
            if (! $summary->archived) {
                $names[$summary->id] = $summary->name;
            }
        }
        $byCenter = $names === [] ? [] : $this->roster->activeIdsInMany(array_keys($names));
        $scope = $center === null ? array_keys($names) : [$center->id];

        return [$names, array_merge([], ...array_values(array_intersect_key($byCenter, array_flip($scope))))];
    }

    /**
     * Where each person stands. One query.
     *
     * @param  list<string>  $people
     * @param  array<string, WorkerAccessLink>  $links
     * @return array<string, string>
     */
    private function states(array $people, array $links): array
    {
        $counts = $this->credentials->countsByEmployee($people);
        $states = [];
        foreach ($people as $id) {
            $states[$id] = match (true) {
                ($counts[$id]->active ?? 0) > 0 => 'with_key',
                isset($links[$id]) && $links[$id]->open() => 'invited',
                default => 'not_invited',
            };
        }

        return $states;
    }

    /**
     * When RH sent the link, and whether it still opens (`sent`, with the hours it has left) or expired.
     *
     * @return array{state: string, sentAt: string, hoursLeft: int|null}
     */
    private static function link(WorkerAccessLink $link): array
    {
        $open = $link->open();

        return [
            'state' => $open ? 'sent' : 'expired',
            'sentAt' => $link->created_at->setTimezone(config()->string('app.display_timezone'))->format('Y-m-d H:i'),
            'hoursLeft' => $open ? (int) ceil(CarbonImmutable::now()->diffInHours($link->expires_at)) : null,
        ];
    }

    /**
     * The keys of the people of a page, the active first and then the newest; local times. One query.
     *
     * @param  list<string>  $people
     * @return array<string, list<array{id: string, label: string, synced: bool, registeredAt: string, lastUsedAt: string|null, revokedAt: string|null}>>
     */
    private function keysOf(array $people): array
    {
        $zone = config()->string('app.display_timezone');
        $local = fn (?CarbonImmutable $at): ?string => $at?->setTimezone($zone)->format('Y-m-d H:i');

        return array_map(fn (array $keys): array => array_map(fn (CredentialSummary $key): array => [
            'id' => $key->id,
            'label' => $key->label,
            // A key that may be backed up lives in the person's passkey account, not provably on one device.
            'synced' => $key->synced,
            'registeredAt' => $key->registeredAt->setTimezone($zone)->format('Y-m-d H:i'),
            'lastUsedAt' => $local($key->lastUsedAt),
            'revokedAt' => $local($key->revokedAt),
        ], $keys), $this->credentials->ofEmployees($people));
    }
}
