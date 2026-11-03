<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

use App\Modules\Complaints\Enums\ComplaintStatus;
use App\Modules\Complaints\Enums\ComplaintType;
use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintAssignment;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The inbox of a handler: the complaints assigned to them and no others, paged in the server, with the counts of its two
 * tabs. A row says what a case is (reference, type, day, center, state) and never what it says: no text of the complaint
 * and nothing of who wrote it reaches the list.
 */
final readonly class InboxPages
{
    private const int PER_PAGE = 15;

    public function __construct(private WorkCenters $workCenters) {}

    /**
     * @return array<string, mixed>
     */
    public function index(int $userId, Request $request): array
    {
        $mine = Complaint::query()->whereIn('id', ComplaintAssignment::query()->where('user_id', $userId)->whereNull('ended_on')->select('complaint_id'));
        $status = $request->string('status')->toString() === 'closed' ? 'closed' : 'open';
        $type = ComplaintType::tryFrom($request->string('type')->toString());
        $center = $request->string('center')->toString();
        $search = strtoupper((string) preg_replace('/[^0-9A-Za-z]/', '', $request->string('q')->toString()));
        $closed = (clone $mine)->where('status', ComplaintStatus::Closed->value)->count();
        $open = (clone $mine)->where('status', '<>', ComplaintStatus::Closed->value)->count();

        $list = $mine
            ->when($status === 'closed', fn ($query) => $query->where('status', ComplaintStatus::Closed->value), fn ($query) => $query->where('status', '<>', ComplaintStatus::Closed->value))
            ->when($type !== null, fn ($query) => $query->where('type', $type?->value))
            ->when(Str::isUuid($center), fn ($query) => $query->where('work_center_id', $center))
            ->when($search !== '', fn ($query) => $query->where('reference', 'like', $search.'%'))
            ->orderByDesc('received_on')
            ->orderBy('id')
            ->paginate(self::PER_PAGE, ['id', 'reference', 'type', 'status', 'resolution', 'received_on', 'work_center_id', 'respondent_kind', 'identity_mode', 'acknowledged_at'])
            ->withQueryString();

        return [
            'cases' => $this->rows($list),
            'status' => $status,
            'counts' => ['open' => $open, 'closed' => $closed],
            'filters' => ['type' => $type?->value, 'center' => $this->center($center), 'q' => $request->string('q')->trim()->limit(20, '')->toString()],
            'types' => array_map(fn (ComplaintType $each): array => ['value' => $each->value, 'label' => $each->label()], ComplaintType::cases()),
        ];
    }

    /**
     * The work center the list is narrowed to, by name; null when none or when it is not one of the company's.
     *
     * @return array{value: string, label: string}|null
     */
    private function center(string $id): ?array
    {
        $found = Str::isUuid($id) ? $this->workCenters->profile($id) : null;

        return $found === null ? null : ['value' => $found->id, 'label' => $found->name];
    }

    /**
     * @param  LengthAwarePaginator<int, Complaint>  $list
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function rows(LengthAwarePaginator $list): LengthAwarePaginator
    {
        $centers = [];

        foreach ($this->workCenters->all() as $center) {
            $centers[$center->id] = $center->name;
        }

        $limit = ComplaintChannel::query()->first()->acknowledge_within_days ?? config()->integer('complaints.deadlines.acknowledge_days');
        $today = Dates::today();

        return $list->through(fn (Complaint $complaint): array => $this->row($complaint, $centers, $limit, $today));
    }

    /**
     * @param  array<string, string>  $centers
     * @return array<string, mixed>
     */
    private function row(Complaint $complaint, array $centers, int $limit, CarbonImmutable $today): array
    {
        return [
            'id' => $complaint->id,
            'reference' => $complaint->reference,
            'type' => $complaint->type->value,
            'typeLabel' => $complaint->type->label(),
            'status' => $complaint->status->value,
            'statusLabel' => $complaint->status->label(),
            'resolution' => $complaint->resolution?->label(),
            'receivedOn' => $complaint->received_on->toDateString(),
            'center' => $complaint->work_center_id === null ? null : ($centers[$complaint->work_center_id] ?? null),
            'kindLabel' => $complaint->respondent_kind->shortLabel(),
            'identity' => $complaint->identity_mode->value,
            'overdue' => $complaint->status === ComplaintStatus::New && $complaint->acknowledged_at === null && $complaint->received_on->addDays($limit)->toDateString() < $today->toDateString(),
        ];
    }
}
