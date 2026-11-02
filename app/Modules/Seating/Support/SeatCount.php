<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;

/**
 * How many seats a position has against how many people use it at once. Seats out of service do not count; one that needs
 * repair does (it is still there), and the board warns about it apart.
 */
final readonly class SeatCount
{
    public function forPosition(string $positionId): SeatCountResult
    {
        return $this->forPositions([$positionId])[$positionId] ?? new SeatCountResult(0, 0, false);
    }

    /**
     * @param  list<string>  $positionIds
     * @return array<string, SeatCountResult> by position id
     */
    public function forPositions(array $positionIds): array
    {
        $seats = [];

        foreach (Seat::query()->whereIn('position_id', $positionIds)->where('condition', '!=', SeatCondition::OutOfService)->get(['position_id', 'quantity']) as $seat) {
            if ($seat->position_id !== null) {
                $seats[$seat->position_id] = ($seats[$seat->position_id] ?? 0) + $seat->quantity;
            }
        }

        $results = [];

        foreach (StandingPosition::query()->whereIn('id', $positionIds)->get(['id', 'max_people_at_once']) as $position) {
            $count = $seats[$position->id] ?? 0;
            $results[$position->id] = new SeatCountResult($count, $position->max_people_at_once, $count < $position->max_people_at_once);
        }

        return $results;
    }
}
