<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Campaigns\Contracts\CampaignRecords;
use App\Modules\Campaigns\Data\CampaignRecord;
use App\Modules\Complaints\Contracts\ComplaintsToAcknowledge;
use App\Modules\Complaints\Data\ComplaintToAcknowledge;
use App\Modules\Leave\Contracts\PendingRequests;
use App\Modules\Seating\Contracts\SeatingRoute;
use App\Modules\Telework\Contracts\TeleworkRoute;
use App\Modules\Telework\Data\UnreviewedAccidents;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;

/**
 * What the company's panel shows (rediseño v2, board V2-Panel): what to attend first, where each work center stands in the
 * route of each standard, and what falls due this week. Each module tells its part through its contracts; the urgencies
 * reach only whoever may act on them, and a complaint is told by its kind and deadline, never by what it says.
 */
final readonly class DashboardPage
{
    private const int CENTERS_PER_PAGE = 5;

    private const int URGENCIES = 5;

    private const int WEEK = 5;

    public function __construct(
        private CampaignRecords $campaigns,
        private Nom035Route $nom035,
        private TeleworkRoute $telework,
        private SeatingRoute $seating,
        private ComplaintsToAcknowledge $complaints,
        private PanelUrgencies $urgencies,
        private PendingRequests $requests,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function props(string $tenantSlug, int $userId, bool $seesScreenings, bool $reviewsNotices, bool $handlesComplaints, bool $managesVacations = false): array
    {
        $page = WorkCenter::query()->whereNull('archived_at')->orderBy('name')->orderBy('id')
            ->paginate(self::CENTERS_PER_PAGE, ['id', 'name'], 'centers')->withQueryString();
        $ids = array_values(array_map(fn (WorkCenter $center): string => $center->id, $page->items()));
        $names = $this->names();
        $current = $this->campaigns->current();
        $latest = $ids === [] ? [] : $this->campaigns->latestClosedEvaluations($ids);
        // Any launched campaign may have Guía I to channel, a Guía I by event included.
        $launched = array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->launchedOn !== null);
        $evaluations = array_values(array_unique(array_map(fn (CampaignRecord $campaign): string => $campaign->id, [...array_values($latest), ...$launched])));
        $toChannel = $seesScreenings && $evaluations !== [] ? $this->nom035->toChannel($evaluations) : [];
        $routes = $ids === [] ? ['nom035' => [], 'nom037' => [], 'seating' => []] : [
            'nom035' => $this->nom035->progress($ids, $current, $latest, $toChannel),
            'nom037' => $this->telework->progress($ids),
            'seating' => $this->seating->progress($ids),
        ];
        $urgencies = [
            ...($reviewsNotices ? array_map(fn (UnreviewedAccidents $accidents): array => $this->urgencies->accident($accidents, $names, $tenantSlug), $this->telework->accidents()) : []),
            ...$this->urgencies->screenings($toChannel, [...array_values($latest), ...$launched], $names, $tenantSlug),
            ...($managesVacations ? $this->urgencies->vacations($this->requests->waiting(), $tenantSlug) : []),
            ...($handlesComplaints ? array_map(fn (ComplaintToAcknowledge $complaint): array => $this->complaint($complaint, $tenantSlug), $this->complaints->soonest($userId, self::URGENCIES)) : []),
        ];

        return [
            'today' => ucfirst($this->today()->isoFormat('dddd D [de] MMMM')),
            // Every route hangs from a work center: until there is one, the panel points there first.
            'hasWorkCenters' => $page->total() > 0,
            'summary' => [
                'norms' => ($ids === [] ? 0 : 1) + (self::anyApplies($routes['nom037']) ? 1 : 0) + (self::anyApplies($routes['seating']) ? 1 : 0),
                'centers' => $page->total(),
            ],
            'urgencies' => array_slice($urgencies, 0, self::URGENCIES),
            'week' => $this->week($current, $names),
            'centers' => $page->through(fn (WorkCenter $center): array => [
                'id' => $center->id,
                'name' => $center->name,
                'nom035' => self::route($routes['nom035'][$center->id]),
                'nom037' => self::route($routes['nom037'][$center->id]),
                'seating' => self::route($routes['seating'][$center->id]),
            ]),
        ];
    }

    /**
     * @return array{norm: string, title: string, detail: string, action: string, href: string}
     */
    private function complaint(ComplaintToAcknowledge $complaint, string $tenantSlug): array
    {
        $today = $this->today();
        $when = match ($complaint->dueOn) {
            $today->toDateString() => 'Vence hoy',
            $today->addDay()->toDateString() => 'Vence mañana',
            default => ($complaint->dueOn < $today->toDateString() ? 'Venció el ' : 'Vence el ').self::day($complaint->dueOn),
        };

        return [
            'norm' => 'Quejas',
            'title' => $complaint->title,
            'detail' => "{$when} · la ves porque te designaron · sin contenido aquí",
            'action' => 'Acusar recibo',
            'href' => route('tenant.complaints.cases.show', ['tenant' => $tenantSlug, 'complaint' => $complaint->id], false),
        ];
    }

    /**
     * The evaluations in course that close within the next seven days, the soonest first.
     *
     * @param  list<CampaignRecord>  $current
     * @param  array<string, string>  $names
     * @return list<array{day: string, text: string}>
     */
    private function week(array $current, array $names): array
    {
        $closing = array_filter($current, fn (CampaignRecord $campaign): bool => $campaign->kind === 'nom035_evaluation' && $campaign->daysLeft !== null && $campaign->daysLeft <= 7);

        return array_slice(array_values(array_map(fn (CampaignRecord $campaign): array => [
            'day' => ucfirst(str_replace('.', '', $this->today()->addDays($campaign->daysLeft ?? 0)->isoFormat('ddd D MMM'))),
            'text' => 'cierra la evaluación NOM-035 de '.($names[$campaign->workCenterId] ?? ''),
        ], $closing)), 0, self::WEEK);
    }

    /** @return array<string, string> every work center's name by id, archived ones included */
    private function names(): array
    {
        $names = [];

        foreach (WorkCenter::query()->get(['id', 'name']) as $center) {
            $names[$center->id] = $center->name;
        }

        return $names;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config()->string('app.display_timezone'));
    }

    /**
     * @param  array<string, RouteProgress>  $routes
     */
    private static function anyApplies(array $routes): bool
    {
        return array_any($routes, fn (RouteProgress $route): bool => $route->applies);
    }

    /**
     * @return array{applies: bool, done: list<bool>, status: string, answered: bool}
     */
    private static function route(RouteProgress $route): array
    {
        return ['applies' => $route->applies, 'done' => $route->done, 'status' => $route->status, 'answered' => $route->answered];
    }

    /** A Y-m-d day as the design system writes it: "27 feb 2026". */
    private static function day(string $date): string
    {
        return str_replace('.', '', CarbonImmutable::parse($date)->isoFormat('D MMM YYYY'));
    }
}
