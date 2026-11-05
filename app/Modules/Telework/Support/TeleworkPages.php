<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Contracts\TeleworkRoute;
use App\Modules\Telework\Data\UnreviewedAccidents;
use App\Modules\Telework\Enums\RowState;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Checklist;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\SafetyCommittee;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Telework\Models\Training;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\MemberSummary;
use App\Modules\Tenancy\Data\RouteProgress;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What the NOM-037 entry pages send to the browser: the list of centers with their progress and the dashboard of one
 * center (the 17 rows of table 10.3, its tabs and its roster). What each person sees follows the spec's «Roles y
 * permisos»: owner and hr everything; the Comisión its centers, with names but without estado civil, phone or home
 * address (C-18); the viewer only counts and row states, never a name.
 */
final readonly class TeleworkPages
{
    private const int CENTERS_PER_PAGE = 12;

    private const int PEOPLE_PER_PAGE = 15;

    /** The rows of table 10.3 in plain words, as an inspector would ask for them (board V3-Inspeccion). */
    private const array PLAIN = [
        '5.1' => 'Lista de personas en teletrabajo',
        '5.1.1' => 'Conexión y condiciones de cada lugar',
        '5.1.2' => 'Cada persona con un lugar fijo y convenido',
        '5.2' => 'Política de Teletrabajo completa y leída',
        '5.3' => 'Los riesgos de trabajar desde casa, explicados',
        '5.4 y 7' => 'Lista de verificación con todos los temas',
        '5.5' => 'Cada lugar revisado y validado por la Comisión',
        '5.5.1' => 'La revisión periódica de cada lugar',
        '5.6' => 'Cómo se empieza a teletrabajar',
        '5.7' => 'Equipo entregado a cada persona',
        '5.8' => 'Mantenimiento del equipo',
        '5.9 y 8' => 'Capacitación del último año',
        '5.10' => 'Regreso a la oficina',
        '5.11' => 'Accidentes avisados, con seguimiento',
        '5.12' => 'Comisión con acceso al expediente',
        '5.13' => 'Apoyo si vive violencia en casa',
        '5.14' => 'Participación en comisiones',
    ];

    public function __construct(
        private VisibleCenters $visible,
        private ComplianceRows $rows,
        private CommitteeGate $gate,
        private Roster $roster,
        private WorkCenters $workCenters,
        private WorkplaceStanding $standing,
        private Settings $settings,
        private Members $members,
        private TeleworkRoute $route,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(int $userId): array
    {
        $page = $this->visible->page($userId, self::CENTERS_PER_PAGE);
        // The items of a page are already a list.
        $ids = array_values(array_map(fn (WorkCenterSummary $center): string => $center->id, $page->items())); // @pest-mutate-ignore: UnwrapArrayValues
        // Everything about the centers of the page is read once for all of them: the page costs the same with two or twelve.
        $rows = $this->rows->forMany($ids);
        $people = $this->headcounts($ids);
        $pending = $this->pendingReviews($ids);

        return ['centers' => $page->through(fn (WorkCenterSummary $center): array => [
            'id' => $center->id,
            'name' => $center->name,
            'activity' => $center->mainActivity,
            'teleworkers' => $people[$center->id]['active'],
            'aspirants' => $people[$center->id]['aspirants'],
            'pendingReviews' => $pending[$center->id] ?? 0,
            ...$this->progress($rows[$center->id] ?? []),
        ])];
    }

    /**
     * @return array<string, mixed>
     */
    public function center(mixed $tenant, WorkCenterSummary $center, int $userId): array
    {
        $manage = Gate::allows('manage-telework');
        $member = $this->gate->isMember($userId, $center->id);
        $rows = $this->rows->for($center->id);
        $reach = ['manage' => $manage, 'member' => $member];
        $people = $this->headcounts([$center->id])[$center->id];
        $active = $people['active'];

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name, 'activity' => $center->mainActivity],
            'progress' => $this->progress($rows),
            'dossierHref' => $manage || $member ? route('tenant.telework.dossier', [$tenant, $center->id]) : null,
            'teleworkers' => $active,
            'aspirants' => $people['aspirants'],
            'rows' => array_map(fn (ComplianceRow $row): array => [
                'numeral' => $row->numeral,
                'label' => $row->label,
                'state' => $row->state->value,
                'stateLabel' => $row->state->label(),
                'detail' => $row->detail,
                ...$this->fix($tenant, $center, $row->numeral, $reach),
            ], $rows),
            'nextStep' => $this->nextStep($tenant, $center, $reach),
            'tabs' => $this->tabs($tenant, $center, $active, $reach),
            'people' => $manage || $member ? $this->people($tenant, $center, $manage) : null,
        ];
    }

    /**
     * The NOM-037 route of one center (board V2-Ruta-N037): where it stands in its six stages, every row of table 10.3 in
     * plain words with the stage it belongs to and its way to fix it, the accidents that cannot wait (for who reviews
     * them) and the way to «Si hoy llegara una inspección».
     *
     * @return array<string, mixed>
     */
    public function route(mixed $tenant, WorkCenterSummary $center, RouteProgress $progress, int $userId): array
    {
        $reach = ['manage' => Gate::allows('manage-telework'), 'member' => $this->gate->isMember($userId, $center->id)];
        $accidents = Gate::allows('review-workplaces') ? array_values(array_filter($this->route->accidents(), fn (UnreviewedAccidents $accidents): bool => $accidents->workCenterId === $center->id)) : [];

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'teleworkers' => $this->headcounts([$center->id])[$center->id]['active'],
            'route' => ['applies' => $progress->applies, 'done' => $progress->done, 'status' => $progress->status, 'answered' => $progress->answered],
            'rows' => array_map(fn (ComplianceRow $row): array => [
                'numeral' => $row->numeral,
                'title' => self::PLAIN[$row->numeral] ?? $row->label,
                'detail' => $row->detail,
                'state' => $row->state->value,
                'stage' => intdiv(RouteStages::rank($row->numeral), 100) + 1,
                ...$this->fix($tenant, $center, $row->numeral, $reach, $row->state === RowState::Done),
            ], $this->rows->for($center->id)),
            'accidents' => array_map(fn (UnreviewedAccidents $accidents): array => [
                'person' => $accidents->personName,
                'count' => $accidents->count,
                'since' => $accidents->since,
                'waiting' => CarbonImmutable::parse($accidents->since, config()->string('app.display_timezone'))->diffForHumans(),
                'href' => $accidents->noticeId === null
                    ? route('tenant.telework.notices.index', [$tenant, 'center' => $center->id])
                    : route('tenant.telework.notices.show', [$tenant, $accidents->noticeId]),
            ], $accidents),
            'inspectionHref' => route('tenant.telework.inspection', [$tenant, $center->id]),
        ];
    }

    /**
     * «Si hoy llegara una inspección» (board V3-Inspeccion): the rows of table 10.3 that apply, split into what the center
     * can show and what is missing (the accidents first, then in the order of the route), each in plain words with its
     * way to fix it for who may; and the dossier for who may read it.
     *
     * @return array<string, mixed>
     */
    public function inspection(mixed $tenant, WorkCenterSummary $center, int $userId): array
    {
        $manage = Gate::allows('manage-telework');
        $member = $this->gate->isMember($userId, $center->id);
        $reach = ['manage' => $manage, 'member' => $member];
        $applicable = array_values(array_filter($this->rows->for($center->id), fn (ComplianceRow $row): bool => $row->state !== RowState::NotApplicable));
        $item = fn (ComplianceRow $row): array => [
            'numeral' => $row->numeral,
            'title' => self::PLAIN[$row->numeral] ?? $row->label,
            'detail' => $row->detail,
            'state' => $row->state->value,
            ...$this->fix($tenant, $center, $row->numeral, $reach, $row->state === RowState::Done),
        ];
        $missing = array_values(array_filter($applicable, fn (ComplianceRow $row): bool => $row->state !== RowState::Done));
        usort($missing, fn (ComplianceRow $a, ComplianceRow $b): int => ($a->numeral === '5.11' ? -1 : RouteStages::rank($a->numeral)) <=> ($b->numeral === '5.11' ? -1 : RouteStages::rank($b->numeral)));

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'applicable' => count($applicable),
            'ready' => array_map($item, array_values(array_filter($applicable, fn (ComplianceRow $row): bool => $row->state === RowState::Done))),
            'missing' => array_map($item, $missing),
            'dossierHref' => $manage || $member ? route('tenant.telework.dossier', [$tenant, $center->id]) : null,
        ];
    }

    /**
     * @param  list<ComplianceRow>  $rows
     * @return array{done: int, applicable: int}
     */
    private function progress(array $rows): array
    {
        $applicable = array_filter($rows, fn (ComplianceRow $row): bool => $row->state !== RowState::NotApplicable);

        return [
            'done' => count(array_filter($applicable, fn (ComplianceRow $row): bool => $row->state === RowState::Done)),
            'applicable' => count($applicable),
        ];
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, array{active: int, aspirants: int}>
     */
    private function headcounts(array $ids): array
    {
        $active = [];
        $aspirants = [];

        foreach (Teleworker::query()->whereIn('work_center_id', $ids)->whereIn('status', [TeleworkerStatus::Active, TeleworkerStatus::Aspirant])->get(['work_center_id', 'status']) as $person) {
            if ($person->status === TeleworkerStatus::Active) {
                $active[$person->work_center_id] = ($active[$person->work_center_id] ?? 0) + 1;
            } else {
                $aspirants[$person->work_center_id] = ($aspirants[$person->work_center_id] ?? 0) + 1;
            }
        }

        $counts = [];

        foreach ($ids as $id) {
            $counts[$id] = ['active' => $active[$id] ?? 0, 'aspirants' => $aspirants[$id] ?? 0];
        }

        return $counts;
    }

    /**
     * Sent checklists nobody has judged yet, of workplaces still in use: what the Comisión's inbox lists, by center.
     *
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    private function pendingReviews(array $ids): array
    {
        $waiting = ChecklistApplication::query()->awaitingReview()
            ->whereIn('workplace_id', Workplace::query()->whereIn('teleworker_id', Teleworker::query()->whereIn('work_center_id', $ids)->select('id'))->select('id'))
            ->get(['workplace_id']);

        if ($waiting->isEmpty()) {
            return [];
        }

        $personOf = [];

        // A whereIn ignores the keys of its values, and repeated values.
        foreach (Workplace::query()->whereIn('id', array_values($waiting->map(fn (ChecklistApplication $application): string => $application->workplace_id)->unique()->all()))->get(['id', 'teleworker_id']) as $workplace) { // @pest-mutate-ignore: UnwrapArrayValues
            $personOf[$workplace->id] = $workplace->teleworker_id;
        }

        $centerOf = [];

        foreach (Teleworker::query()->whereIn('id', array_values(array_unique($personOf)))->get(['id', 'work_center_id']) as $person) { // @pest-mutate-ignore: UnwrapArrayValues,UnwrapArrayUnique
            $centerOf[$person->id] = $person->work_center_id;
        }

        $counts = [];

        foreach ($waiting as $application) {
            $center = $centerOf[$personOf[$application->workplace_id]] ?? null;

            if ($center !== null) {
                $counts[$center] = ($counts[$center] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * The first thing a center lacks to start: its Comisión, then a published list (criterio C-17).
     *
     * @param  array{manage: bool, member: bool}  $reach
     * @return array{kind: string, title: string, body: string, cta: string, href: string|null}|null
     */
    private function nextStep(mixed $tenant, WorkCenterSummary $center, array $reach): ?array
    {
        $href = $reach['manage'] || $reach['member'] ? route('tenant.telework.committee.show', [$tenant, $center->id]) : null;

        if (! SafetyCommittee::query()->where('work_center_id', $center->id)->exists()) {
            return [
                'kind' => 'committee',
                'title' => 'Registra la Comisión',
                'body' => 'NOM-037 empieza por la Comisión de seguridad e higiene del centro: ella publica la lista de verificación y valida los lugares de trabajo.',
                'cta' => 'Registrar la Comisión',
                'href' => $href,
            ];
        }

        if (Checklist::latestPublished($center->id) === null) {
            return [
                'kind' => 'checklist',
                'title' => 'Que la Comisión publique la lista',
                'body' => 'Mientras la lista de verificación no esté publicada, nadie puede contestarla y ningún lugar de trabajo se valida.',
                'cta' => 'Ver la lista',
                'href' => $reach['manage'] || $reach['member'] ? route('tenant.telework.checklist.show', [$tenant, $center->id]) : null,
            ];
        }

        return null;
    }

    /**
     * Where to fix a row, when the person may open it: nothing for the viewer. A row already done opens where its evidence is.
     *
     * @param  array{manage: bool, member: bool}  $reach
     * @return array{href: string|null, action: string|null}
     */
    private function fix(mixed $tenant, WorkCenterSummary $center, string $numeral, array $reach, bool $done = false): array
    {
        $none = ['href' => null, 'action' => null];

        if (! $reach['manage'] && ! $reach['member']) {
            return $none;
        }

        $documents = ['href' => route('tenant.telework.documents.index', [$tenant, 'center' => $center->id]), 'action' => 'Ver documentos'];

        return match ($numeral) {
            '5.1' => match (true) {
                $done => ['href' => route('tenant.telework.center', [$tenant, $center->id]), 'action' => 'Ver personas'],
                $reach['manage'] => ['href' => route('tenant.telework.teleworkers.create', [$tenant, $center->id]), 'action' => 'Registrar persona'],
                default => $none,
            },
            '5.1.1', '5.1.2', '5.5' => $reach['member'] ? ['href' => route('tenant.telework.reviews.index', [$tenant, $center->id]), 'action' => 'Ver las listas por revisar'] : $none,
            '5.4 y 7', '5.5.1' => ['href' => route('tenant.telework.checklist.show', [$tenant, $center->id]), 'action' => 'Ver la lista'],
            '5.7' => $reach['manage'] ? ['href' => route('tenant.telework.center', [$tenant, $center->id]).'#padron', 'action' => 'Ver el padrón'] : $none,
            '5.9 y 8' => $reach['manage'] ? ['href' => route('tenant.telework.trainings.index', [$tenant, 'center' => $center->id]), 'action' => 'Ver capacitaciones'] : $none,
            '5.11' => ['href' => route('tenant.telework.notices.index', [$tenant, 'center' => $center->id]), 'action' => 'Ver los avisos'],
            '5.12' => ['href' => route('tenant.telework.committee.show', [$tenant, $center->id]), 'action' => 'Ver la Comisión'],
            default => $documents,
        };
    }

    /**
     * The views of the center a person can open, each with how many it holds (the active tab is the page itself).
     *
     * @param  array{manage: bool, member: bool}  $reach
     * @return list<array{key: string, label: string, count: int, href: string, active: bool}>
     */
    private function tabs(mixed $tenant, WorkCenterSummary $center, int $active, array $reach): array
    {
        $either = $reach['manage'] || $reach['member'];
        $reviewers = array_map(fn (MemberSummary $member): int => $member->userId, $this->members->reviewers());
        $tab = fn (string $key, string $label, int $count, string $href, bool $current = false): array => ['key' => $key, 'label' => $label, 'count' => $count, 'href' => $href, 'active' => $current];

        return [
            $tab('people', 'Padrón', $active, route('tenant.telework.center', [$tenant, $center->id]), true),
            ...($reach['member'] ? [$tab('review', 'Por validar', ($this->pendingReviews([$center->id])[$center->id] ?? 0), route('tenant.telework.reviews.index', [$tenant, $center->id]))] : []),
            $tab('documents', 'Documentos', TeleworkDocument::query()->inForce()->where('work_center_id', $center->id)->count(), route('tenant.telework.documents.index', [$tenant, 'center' => $center->id])),
            ...($reach['manage'] ? [$tab('trainings', 'Capacitación', Training::query()->where('work_center_id', $center->id)->count(), route('tenant.telework.trainings.index', [$tenant, 'center' => $center->id]))] : []),
            ...($either ? [
                $tab('notices', 'Avisos', WorkerNotice::query()->whereIn('teleworker_id', Teleworker::query()->where('work_center_id', $center->id)->select('id'))->count(), route('tenant.telework.notices.index', [$tenant, 'center' => $center->id])),
                $tab('committee', 'Comisión', CommitteeAssignment::query()->where('work_center_id', $center->id)->whereIn('user_id', $reviewers)->count(), route('tenant.telework.committee.show', [$tenant, $center->id])),
            ] : []),
        ];
    }

    /**
     * The roster page: active people first, with where each of their workplaces stands. RH also gets the 5.1 personal
     * data; the Comisión does not (criterios C-06, C-18).
     *
     * @return LengthAwarePaginator<int, array{id: string, name: string, jobTitle: string, percentage: int, status: string, workplaces: list<array{id: string, status: string, statusLabel: string}>, href: string|null, maritalStatus?: string|null, contactPhone?: string|null, homeAddress?: string|null}>
     */
    private function people(mixed $tenant, WorkCenterSummary $center, bool $manage): LengthAwarePaginator
    {
        $page = Teleworker::query()->where('work_center_id', $center->id)->whereIn('status', [TeleworkerStatus::Active, TeleworkerStatus::Aspirant])
            ->orderBy('status')->orderBy('created_at')->orderBy('id')->paginate(self::PEOPLE_PER_PAGE)->withQueryString();
        // The items of a page are already a list, and latestReviews only chunks its ids into a whereIn.
        $names = $this->roster->findMany(array_values(array_map(fn (Teleworker $person): string => $person->employee_id, $page->items()))); // @pest-mutate-ignore: UnwrapArrayValues
        $places = Workplace::query()->whereNull('retired_at')->whereIn('teleworker_id', array_map(fn (Teleworker $person): string => $person->id, $page->items()))->orderBy('created_at')->orderBy('id')->get(['id', 'teleworker_id']);
        $reviews = $this->standing->latestReviews(array_values($places->map(fn (Workplace $place): string => $place->id)->all())); // @pest-mutate-ignore: UnwrapArrayValues
        $months = $this->settings->periodMonths();
        $states = $this->workCenters->states();

        return $page->through(function (Teleworker $person) use ($tenant, $manage, $names, $places, $reviews, $months, $states): array {
            // The foreign key guarantees the employee of every period.
            $entry = $names[$person->employee_id];

            return [
                'id' => $person->id,
                'name' => $entry->name,
                'jobTitle' => $entry->jobTitle,
                'percentage' => $person->telework_percentage,
                'status' => $person->status->value,
                'workplaces' => array_values($places->where('teleworker_id', $person->id)->map(function (Workplace $place) use ($reviews, $months): array {
                    $status = $this->standing->statusOf($reviews[$place->id] ?? null, fn (): int => $months);

                    return ['id' => $place->id, 'status' => $status->value, 'statusLabel' => $status->label()];
                })->all()),
                'href' => $manage ? route('tenant.telework.teleworkers.show', [$tenant, $person->id]) : null,
                ...($manage ? [
                    'maritalStatus' => $person->marital_status?->value,
                    'contactPhone' => $person->contact_phone,
                    'homeAddress' => $person->homeAddress($states),
                ] : []),
            ];
        });
    }
}
