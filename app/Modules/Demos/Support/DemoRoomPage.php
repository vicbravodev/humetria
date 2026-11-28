<?php

declare(strict_types=1);

namespace App\Modules\Demos\Support;

use App\Modules\Demos\Contracts\DemoGuide;
use App\Modules\Demos\Contracts\DemoShowcase;
use App\Modules\Demos\Contracts\DoorControl;
use App\Modules\Demos\Data\TourStep;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Enums\TourKey;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Context;
use Spatie\Permission\PermissionRegistrar;

/**
 * What the room shows: the demo company, the run preparing it, how old it is, its complaint channel and the door; the tours
 * a session may show and the session in course (or the last one, when it failed) with each tour's progress. Counts and
 * links only: no person of the demo.
 *
 * @phpstan-import-type Guide from DemoGuide
 * @phpstan-import-type GuideTour from DemoGuide
 */
final readonly class DemoRoomPage
{
    /**
     * Older than this, the story goes stale in front of an audience: a complaint already overdue, pending leave requests
     * timing out, open surveys about to close.
     */
    public const int FRESH_DAYS = 2;

    public function __construct(
        private DemoCompanies $demos,
        private CompanyRegistry $companies,
        private DemoShowcase $showcase,
        private DoorControl $door,
        private QrCode $qr,
        private Companies $tenants,
        private SessionGuide $guide,
        private TourCatalog $catalog,
        private PermissionRegistrar $permissions,
    ) {}

    /** @return array<string, mixed> */
    public function props(): array
    {
        $id = $this->demos->id();
        $company = $id === null ? null : $this->companies->find($id);
        $run = DemoRun::current();
        $channel = $company === null ? null : $this->showcase->complaintChannelUrl();

        return [
            'company' => $company === null ? null : ['id' => $company->id, 'name' => $company->tradeName],
            'run' => $run === null ? null : ['kind' => $run->kind, 'status' => $run->status, 'step' => $run->step, 'error_class' => $run->error_class],
            'prepared' => $company === null ? null : $this->prepared(),
            'complaints' => $channel === null ? null : ['url' => $channel, 'qr' => $this->qr->dataUri($channel)],
            'door' => $this->door(),
            'tours' => array_map(fn (TourKey $tour): array => ['key' => $tour->value, 'title' => $tour->title(), 'summary' => $tour->summary(), 'minutes' => $tour->minutes()], array_values(array_filter(TourKey::cases(), fn (TourKey $tour): bool => $tour !== TourKey::Base))),
            'session' => $this->session(),
        ];
    }

    /**
     * The session in course, or the last one when it failed (until it is ended); null once the last one ended.
     *
     * @return array{client: ?string, status: string, failed: bool, since: string, tours: list<GuideTour>, next: array{title: string, tour: string, index: int, total: int, href: string}|null}|null
     */
    private function session(): ?array
    {
        $session = DemoSession::query()->latest('started_at')->first();

        if ($session === null || $session->status === DemoSessionStatus::Ended) {
            return null;
        }

        $guide = $session->status === DemoSessionStatus::Active ? $this->guideOf($session) : null;
        $next = $guide['current'] ?? null;

        return [
            'client' => $session->client_name,
            'status' => $session->status->value,
            'failed' => $session->status === DemoSessionStatus::Failed,
            'since' => $session->since()->diffForHumans(),
            'tours' => $guide['tours'] ?? $this->planned($session),
            'next' => $next === null ? null : [
                'title' => $next['title'],
                'tour' => $next['tour'],
                'index' => $next['index'],
                'total' => $next['total'],
                // A path inside the demo: «Entrar y seguir el guía» lands there after entering it.
                'href' => parse_url($next['href'], PHP_URL_PATH).(($query = parse_url($next['href'], PHP_URL_QUERY)) === null || $query === false ? '' : "?{$query}"),
            ],
        ];
    }

    /**
     * The guide of the active session, read inside the demo company and only for this: the console's request must not go on
     * as the demo. Entering also sets Spatie's team, which Context::scope does not restore: it is put back by hand.
     *
     * @return Guide|null
     */
    private function guideOf(DemoSession $session): ?array
    {
        $demoId = $this->demos->id();

        if ($demoId === null) {
            return null;
        }

        $team = $this->permissions->getPermissionsTeamId();
        $guide = null;

        try {
            Context::scope(function () use ($session, $demoId, &$guide): void {
                $guide = $this->guide->overview($session, $this->tenants->enter($demoId)->slug);
            });
        } finally {
            $this->permissions->setPermissionsTeamId($team);
        }

        return $guide;
    }

    /**
     * The session's tours, nothing done yet: while it is prepared, ends or after it failed there is no progress to read.
     *
     * @return list<GuideTour>
     */
    private function planned(DemoSession $session): array
    {
        $steps = $this->catalog->for($this->catalog->toursOf($session));
        $tours = array_values(array_unique(array_map(fn (TourStep $step): TourKey => $step->tour, $steps), SORT_REGULAR));

        return array_map(fn (TourKey $tour): array => [
            'key' => $tour->value,
            'title' => $tour->title(),
            'done' => 0,
            'total' => count(array_filter($steps, fn (TourStep $step): bool => $step->tour === $tour)),
        ], $tours);
    }

    /** @return array{ago: string, at: string, stale: bool}|null */
    private function prepared(): ?array
    {
        $at = DemoRun::query()->where('status', 'finished')->latest('finished_at')->first()?->finished_at;

        if ($at === null) {
            return null;
        }

        return [
            'ago' => $at->diffForHumans(),
            // Carbon's Spanish months end in a dot ("nov."); the design system writes "16 nov 2026".
            'at' => str_replace('.', '', $at->setTimezone(config()->string('app.display_timezone'))->isoFormat('D MMM YYYY, HH:mm')),
            'stale' => $at->lt(now()->subDays(self::FRESH_DAYS)),
        ];
    }

    /** @return array{open: bool, closes_at: ?string, claimed: int, free: int, total: int} */
    private function door(): array
    {
        $state = $this->door->state();
        $expiresAt = $state['expires_at'];

        return [
            'open' => $state['open'],
            'closes_at' => $expiresAt === null ? null : CarbonImmutable::parse($expiresAt)->setTimezone(config()->string('app.display_timezone'))->format('H:i'),
            'claimed' => $state['claimed'],
            'free' => $state['free'],
            'total' => $state['total'],
        ];
    }
}
