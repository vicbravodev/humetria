<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Organization\Contracts\Roster;
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
use App\Modules\Tenancy\Data\WorkCenterSummary;
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

    public function __construct(
        private VisibleCenters $visible,
        private ComplianceRows $rows,
        private CommitteeGate $gate,
        private Roster $roster,
        private WorkCenters $workCenters,
        private WorkplaceStanding $standing,
        private Settings $settings,
        private Members $members,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function index(int $userId): array
    {
        $page = $this->visible->page($userId, self::CENTERS_PER_PAGE);
        $ids = array_values(array_map(fn (WorkCenterSummary $center): string => $center->id, $page->items()));
        // Everything about the centers of the page is read once for all of them: the page costs the same with two or twelve.
        $rows = $this->rows->forMany($ids);
        $people = $this->headcounts($ids);
        $pending = $this->pendingReviews($ids);

        return ['centers' => $page->through(fn (WorkCenterSummary $center): array => [
            'id' => $center->id,
            'name' => $center->name,
            'activity' => $center->mainActivity,
            'teleworkers' => $people[$center->id]['active'] ?? 0,
            'aspirants' => $people[$center->id]['aspirants'] ?? 0,
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
        $people = $this->headcounts([$center->id])[$center->id] ?? ['active' => 0, 'aspirants' => 0];
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

        foreach (Workplace::query()->whereIn('id', array_values($waiting->map(fn (ChecklistApplication $application): string => $application->workplace_id)->unique()->all()))->get(['id', 'teleworker_id']) as $workplace) {
            $personOf[$workplace->id] = $workplace->teleworker_id;
        }

        $centerOf = [];

        foreach (Teleworker::query()->whereIn('id', array_values(array_unique($personOf)))->get(['id', 'work_center_id']) as $person) {
            $centerOf[$person->id] = $person->work_center_id;
        }

        $counts = [];

        foreach ($waiting as $application) {
            $center = $centerOf[$personOf[$application->workplace_id] ?? ''] ?? null;

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
     * Where to fix a row, when the person may open it: nothing for the viewer.
     *
     * @param  array{manage: bool, member: bool}  $reach
     * @return array{href: string|null, action: string|null}
     */
    private function fix(mixed $tenant, WorkCenterSummary $center, string $numeral, array $reach): array
    {
        $none = ['href' => null, 'action' => null];

        if (! $reach['manage'] && ! $reach['member']) {
            return $none;
        }

        $documents = ['href' => route('tenant.telework.documents.index', [$tenant, 'center' => $center->id]), 'action' => 'Ver documentos'];

        return match ($numeral) {
            '5.1' => $reach['manage'] ? ['href' => route('tenant.telework.teleworkers.create', [$tenant, $center->id]), 'action' => 'Registrar persona'] : $none,
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
        $names = $this->roster->findMany(array_values(array_map(fn (Teleworker $person): string => $person->employee_id, $page->items())));
        $places = Workplace::query()->whereNull('retired_at')->whereIn('teleworker_id', array_map(fn (Teleworker $person): string => $person->id, $page->items()))->orderBy('created_at')->orderBy('id')->get(['id', 'teleworker_id']);
        $reviews = $this->standing->latestReviews(array_values($places->map(fn (Workplace $place): string => $place->id)->all()));
        $months = $this->settings->periodMonths();
        $states = $this->workCenters->states();

        return $page->through(function (Teleworker $person) use ($tenant, $manage, $names, $places, $reviews, $months, $states): array {
            $entry = $names[$person->employee_id] ?? null;

            return [
                'id' => $person->id,
                'name' => $entry === null ? '' : $entry->name,
                'jobTitle' => $entry === null ? '' : $entry->jobTitle,
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
