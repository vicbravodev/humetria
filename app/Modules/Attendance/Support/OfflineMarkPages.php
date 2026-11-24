<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkMethod;
use App\Modules\Attendance\Enums\ReviewFlag;
use App\Modules\Attendance\Models\AttendanceOfflineMark;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Tenancy\Contracts\Timezones;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use LogicException;

/**
 * What the two tabs of marks made without a connection send to the browser (criterio 5.4), as the queue of corrections
 * does: in `offlineMarks`, 25 at a time, searched by the person's name.
 *
 * `offline`: the marks that count and have no review yet (with a QR or without), the ones with flags first and then the
 * oldest. Each row: the person (name and job title, from the Roster), the type, the time counted at the place it was taken,
 * how it was bounded (`basis`), the flags and the days since it arrived. The one in view (`selectedId`, `detail`) also names
 * every hour (declared, ticket, window, arrival, the phone's clock: a hint, never proof) and who signed it (the kind of key,
 * never which), and, only for whoever manages attendance, the note the person wrote (free text that may be medical).
 *
 * `not_accepted`: the deliveries kept as rejected in the last 35 days, newest first, read-only: the person, the type, the
 * time declared, why and when it arrived.
 *
 * Never a position, a key, a ticket, a QR, a challenge nor a device.
 */
final readonly class OfflineMarkPages
{
    private const int PER_PAGE = 25;

    /** How long a rejected delivery is shown: the horizon of a correction. */
    private const int REJECTED_DAYS = 35;

    private const string FORMAT = 'Y-m-d H:i';

    public function __construct(private Roster $roster, private Timezones $zones) {}

    /**
     * @return array<string, mixed>
     */
    public function index(string $view, string $search, bool $unflaggedOnly, string $markId = ''): array
    {
        return [
            'filters' => ['q' => $search, 'flags' => $unflaggedOnly ? 'none' : ''],
            'canResolve' => Gate::allows('manage-attendance'),
            ...($view === 'not_accepted' ? $this->notAccepted($view, $search) : $this->toVerify($view, $search, $unflaggedOnly, $markId)),
        ];
    }

    /**
     * @return array{offline: int, not_accepted: int}
     */
    public function counts(): array
    {
        return ['offline' => self::pending()->count(), 'not_accepted' => $this->rejected()->count()];
    }

    /**
     * The marks that ask for RH's attention (the count of «Necesita tu atención» and of Humi): of the ones to verify, those
     * with flags or that arrived more than `attendance.offline.review_age_days` ago. One query; a number only.
     */
    public static function needsReviewCount(): int
    {
        return self::pending()->where(fn (Builder $query) => $query
            ->whereRaw('jsonb_array_length(review_flags) > 0')
            ->orWhere('received_at', '<', CarbonImmutable::now()->subDays(config()->integer('attendance.offline.review_age_days'))))->count();
    }

    /**
     * The deliveries whose mark counts and nobody reviewed yet.
     *
     * @return Builder<AttendanceOfflineMark>
     */
    private static function pending(): Builder
    {
        return AttendanceOfflineMark::query()->where('outcome', 'accepted')->whereIn(
            'mark_id',
            EffectiveMarks::query()->select('id')
                ->whereIn('method', [MarkMethod::OfflineQr->value, MarkMethod::OfflineDeclared->value])
                ->whereNotExists(fn (QueryBuilder $query) => $query->from('attendance_mark_reviews')->whereColumn('attendance_mark_reviews.mark_id', 'attendance_marks.id')),
        );
    }

    /**
     * @return Builder<AttendanceOfflineMark>
     */
    private function rejected(): Builder
    {
        return AttendanceOfflineMark::query()->where('outcome', 'rejected')->where('received_at', '>=', CarbonImmutable::now()->subDays(self::REJECTED_DAYS));
    }

    /**
     * @return array<string, mixed>
     */
    private function toVerify(string $view, string $search, bool $unflaggedOnly, string $markId): array
    {
        $page = self::pending()
            ->when($unflaggedOnly, fn (Builder $query) => $query->whereRaw('jsonb_array_length(review_flags) = 0'))
            ->when($search !== '', fn (Builder $query) => $query->whereIn('employee_id', $this->roster->matchingIds(new RosterQuery(search: $search))))
            ->with('mark')
            // Flagged first (false sorts before true), then the one that has waited longest.
            ->orderByRaw('jsonb_array_length(review_flags) = 0')->orderBy('received_at')->orderBy('id')
            ->paginate(self::PER_PAGE)->appends(['status' => $view, 'q' => $search, 'flags' => $unflaggedOnly ? 'none' : '']);
        $asked = Str::isUuid($markId) ? self::pending()->with('mark')->where('mark_id', $markId)->first() : null;
        $selected = $asked ?? $page->items()[0] ?? null;
        $people = $this->roster->findMany(array_values(array_unique(array_map(fn (AttendanceOfflineMark $row): string => $row->employee_id, [...$page->items(), ...($selected === null ? [] : [$selected])]))));
        $row = fn (AttendanceOfflineMark $delivery): array => $this->row($delivery, $people[$delivery->employee_id]->name ?? '—', $people[$delivery->employee_id]->jobTitle ?? '');

        return [
            'offlineMarks' => $page->through($row),
            'selectedId' => $selected?->mark_id,
            'detail' => $selected === null ? null : $this->detail($selected, $row($selected)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function notAccepted(string $view, string $search): array
    {
        $page = $this->rejected()
            ->when($search !== '', fn (Builder $query) => $query->whereIn('employee_id', $this->roster->matchingIds(new RosterQuery(search: $search))))
            ->orderByDesc('received_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE)->appends(['status' => $view, 'q' => $search]);
        $people = $this->roster->findMany(array_values(array_unique(array_map(fn (AttendanceOfflineMark $row): string => $row->employee_id, $page->items()))));
        $zones = $this->zones->forCenters(array_values(array_unique(array_map(fn (AttendanceOfflineMark $row): string => $row->work_center_id, $page->items()))));

        return [
            'offlineMarks' => $page->through(fn (AttendanceOfflineMark $row): array => [
                'id' => $row->id,
                'person' => ['name' => $people[$row->employee_id]->name ?? '—', 'jobTitle' => $people[$row->employee_id]->jobTitle ?? ''],
                'type' => $row->mark_type->value,
                'declaredAt' => $row->declared_at->setTimezone($zones[$row->work_center_id])->format(self::FORMAT),
                'reason' => $row->reason?->value,
                'receivedAt' => $row->received_at->setTimezone($zones[$row->work_center_id])->format(self::FORMAT),
            ]),
            'selectedId' => null,
            'detail' => null,
        ];
    }

    /**
     * One mark of the list, in the zone it was taken.
     *
     * @return array{id: string, person: array{name: string, jobTitle: string}, type: string, countedAt: string, basis: string, flags: list<string>, ageDays: int}
     */
    private function row(AttendanceOfflineMark $delivery, string $name, string $jobTitle): array
    {
        $zone = $this->zoneOf($delivery);

        return [
            'id' => $delivery->mark_id ?? throw new LogicException("Delivery [{$delivery->id}] has no mark."),
            'person' => ['name' => $name, 'jobTitle' => $jobTitle],
            'type' => $delivery->mark_type->value,
            'countedAt' => $this->local($delivery->counted_at, $zone),
            'basis' => ($delivery->time_basis ?? throw new LogicException("Delivery [{$delivery->id}] has no basis."))->value,
            'flags' => array_values(array_map(fn (ReviewFlag $flag): string => $flag->value, $delivery->review_flags->all())),
            'ageDays' => (int) $delivery->received_at->setTimezone($zone)->startOfDay()->diffInDays(CarbonImmutable::now($zone)->startOfDay()),
        ];
    }

    /**
     * The mark in view: its row and every hour named, in the zone it was taken.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function detail(AttendanceOfflineMark $delivery, array $row): array
    {
        $zone = $this->zoneOf($delivery);
        $counted = $this->local($delivery->counted_at, $zone);
        $declared = $this->local($delivery->declared_at, $zone);

        return [
            ...$row,
            'declaredAt' => $declared,
            'ticketIssuedAt' => $this->local($delivery->ticket_issued_at, $zone),
            // Only a QR proves «not before».
            'windowStart' => $delivery->window_start === null ? null : $this->local($delivery->window_start, $zone),
            'receivedAt' => $this->local($delivery->received_at, $zone),
            'phoneClockAt' => $delivery->device_clock_at === null ? null : $this->local($delivery->device_clock_at, $zone),
            'signer' => $delivery->signer_type->value,
            // Free text the person wrote, which may be medical: only whoever manages attendance reads it.
            'justification' => Gate::allows('manage-attendance') ? $delivery->justification : null,
            // What the dialog of «Es incorrecta» proposes: the time the person declared when the rule counted another.
            'proposedAt' => $declared === $counted ? $counted : $declared,
        ];
    }

    private function zoneOf(AttendanceOfflineMark $delivery): string
    {
        return $delivery->mark->timezone ?? throw new LogicException("Delivery [{$delivery->id}] has no mark.");
    }

    private function local(?CarbonImmutable $at, string $zone): string
    {
        return ($at ?? throw new LogicException('A delivery that counts has all its times.'))->setTimezone($zone)->format(self::FORMAT);
    }
}
