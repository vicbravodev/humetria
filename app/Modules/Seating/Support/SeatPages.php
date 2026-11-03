<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatFeature;
use App\Modules\Seating\Enums\SeatKind;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Tenancy\Data\WorkCenterSummary;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * What the seat inventory page sends to the browser: the seats of a center, a page at a time, the one being edited, and
 * the positions that have fewer seats than people at once (an aid, not a standard).
 */
final readonly class SeatPages
{
    private const int SEATS_PER_PAGE = 15;

    public const string SHORTFALL_NOTE = 'La ley no fija cuántos asientos corresponden por persona: esta comparación es solo una ayuda para ver dónde probablemente faltan.';

    public function __construct(private SeatCount $count) {}

    /**
     * @return array<string, mixed>
     */
    public function index(mixed $tenant, WorkCenterSummary $center, ?string $editing): array
    {
        $manage = Gate::allows('manage-seating');

        return [
            'workCenter' => ['id' => $center->id, 'name' => $center->name],
            'seats' => $this->seats($tenant, $center->id, $manage || Gate::allows('assess-seating')),
            'editing' => $manage && $editing !== null ? $this->editing($editing, $center->id) : null,
            'shortfalls' => $this->shortfalls($center->id),
            'shortfallNote' => self::SHORTFALL_NOTE,
            'canManage' => $manage,
            'kinds' => array_map(fn (SeatKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], SeatKind::cases()),
            'uses' => array_map(fn (SeatUse $use): array => ['value' => $use->value, 'label' => $use->label()], SeatUse::cases()),
            'conditions' => array_map(fn (SeatCondition $condition): array => ['value' => $condition->value, 'label' => $condition->label()], SeatCondition::cases()),
            'features' => array_map(fn (SeatFeature $feature): array => ['value' => $feature->value, 'label' => $feature->label(), 'hint' => $feature->hint()], SeatFeature::cases()),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: string, kindLabel: string, useLabel: string, quantity: int, location: string|null, positionName: string|null, conditionLabel: string, condition: string, signposted: bool, features: int, photoHref: string|null}>
     */
    private function seats(mixed $tenant, string $workCenterId, bool $photos): LengthAwarePaginator
    {
        $page = Seat::query()->where('work_center_id', $workCenterId)->orderBy('use')->orderBy('location')->orderBy('id')->paginate(self::SEATS_PER_PAGE)->withQueryString();
        $ids = array_values(array_filter(array_map(fn (Seat $seat): ?string => $seat->position_id, $page->items())));
        $names = [];

        foreach (StandingPosition::query()->whereIn('id', $ids)->get(['id', 'name']) as $position) {
            $names[$position->id] = $position->name;
        }

        return $page->through(fn (Seat $seat): array => [
            'id' => $seat->id,
            'kindLabel' => $seat->kind->label(),
            'useLabel' => $seat->use->label(),
            'quantity' => $seat->quantity,
            'location' => $seat->location,
            'positionName' => $seat->position_id === null ? null : ($names[$seat->position_id] ?? null),
            'conditionLabel' => $seat->condition->label(),
            'condition' => $seat->condition->value,
            'signposted' => $seat->signposted,
            'features' => count(array_filter(array_map(fn (SeatFeature $feature): bool => (bool) $seat->getAttribute($feature->value), SeatFeature::cases()))),
            'photoHref' => $photos && $seat->photo_path !== null ? route('tenant.seating.seats.photo', [$tenant, $seat->id]) : null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function editing(string $id, string $workCenterId): ?array
    {
        $seat = Seat::query()->where('work_center_id', $workCenterId)->find($id);

        if ($seat === null) {
            return null;
        }

        return [
            'id' => $seat->id,
            'kind' => $seat->kind->value,
            'use' => $seat->use->value,
            'quantity' => $seat->quantity,
            'location' => $seat->location,
            'positionId' => $seat->position_id,
            'positionName' => $seat->position_id === null ? null : StandingPosition::query()->find($seat->position_id)?->name,
            'features' => array_combine(array_map(fn (SeatFeature $feature): string => $feature->value, SeatFeature::cases()), array_map(fn (SeatFeature $feature): bool => (bool) $seat->getAttribute($feature->value), SeatFeature::cases())),
            'condition' => $seat->condition->value,
            'signposted' => $seat->signposted,
            'hasPhoto' => $seat->photo_path !== null,
        ];
    }

    /**
     * @return list<array{positionId: string, name: string, seats: int, maxPeopleAtOnce: int}>
     */
    private function shortfalls(string $workCenterId): array
    {
        $positions = StandingPosition::query()->where('work_center_id', $workCenterId)->orderBy('name')->get(['id', 'name']);
        $counts = $this->count->forPositions(array_values($positions->map(fn (StandingPosition $position): string => $position->id)->all()));
        $short = [];

        foreach ($positions as $position) {
            $result = $counts[$position->id] ?? null;

            if ($result !== null && $result->short) {
                $short[] = ['positionId' => $position->id, 'name' => $position->name, 'seats' => $result->seats, 'maxPeopleAtOnce' => $result->maxPeopleAtOnce];
            }
        }

        return $short;
    }
}
