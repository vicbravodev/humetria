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
use App\Modules\Telework\Models\WorkplaceReview;
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
        // ponytail: the rows of each center of the page are read one center at a time (about twenty queries each); a
        // batch across centers if a page of twelve ever feels slow.
        return ['centers' => $this->visible->page($userId, self::CENTERS_PER_PAGE)->through(function (WorkCenterSummary $center): array {
            $progress = $this->progress($this->rows->for($center->id));

            return [
                'id' => $center->id,
                'name' => $center->name,
                'activity' => $center->mainActivity,
                'teleworkers' => $this->headcount($center->id, TeleworkerStatus::Active),
                'aspirants' => $this->headcount($center->id, TeleworkerStatus::Aspirant),
                'pendingReviews' => $this->pendingReviews($center->id),
                ...$progress,
            ];
        })];
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
        $active = $this->headcount($center->id, TeleworkerStatus::Active);

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name, 'activity' => $center->mainActivity],
            'progress' => $this->progress($rows),
            'teleworkers' => $active,
            'aspirants' => $this->headcount($center->id, TeleworkerStatus::Aspirant),
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

    private function headcount(string $workCenterId, TeleworkerStatus $status): int
    {
        return Teleworker::query()->where('work_center_id', $workCenterId)->where('status', $status)->count();
    }

    /** Sent checklists nobody has judged yet, of workplaces still in use: what the Comisión's inbox lists. */
    private function pendingReviews(string $workCenterId): int
    {
        return ChecklistApplication::query()->whereNotNull('submitted_at')->whereNull('cancelled_at')
            ->whereNotIn('id', WorkplaceReview::query()->whereNotNull('application_id')->select('application_id'))
            ->whereIn('workplace_id', Workplace::query()->whereNull('retired_at')->whereIn('teleworker_id', Teleworker::query()->where('work_center_id', $workCenterId)->select('id'))->select('id'))
            ->count();
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
            ...($reach['member'] ? [$tab('review', 'Por validar', $this->pendingReviews($center->id), route('tenant.telework.reviews.index', [$tenant, $center->id]))] : []),
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
     * @return LengthAwarePaginator<int, array<string, mixed>>
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

            return $this->props([
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
                    'homeAddress' => $this->homeAddress($person, $states),
                ] : []),
            ]);
        });
    }

    /**
     * A page prop as a plain map: what Inertia sends is not narrower than its keys.
     *
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function props(array $props): array
    {
        return $props;
    }

    /** @param array<string, string> $states */
    private function homeAddress(Teleworker $person, array $states): ?string
    {
        if ($person->street === null) {
            return null;
        }

        $number = $person->interior_number === null ? $person->exterior_number : "{$person->exterior_number}, int. {$person->interior_number}";

        return "{$person->street} {$number}, {$person->neighborhood}, {$person->postal_code} {$person->municipality}, ".($states[$person->state ?? ''] ?? $person->state);
    }
}
