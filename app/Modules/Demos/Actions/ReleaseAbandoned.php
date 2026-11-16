<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Models\DoorAssignment;
use App\Modules\Demos\Support\DemoDoor;
use Illuminate\Support\Facades\Log;

/**
 * Frees the door people handed out more than 30 minutes ago, for the next visitors. The door never learns whether someone
 * opened the portal or answered (nothing ties a visitor to an answer, and no contract tells), so this is only for a full
 * room of people who walked away: a freed worker whose visitor did answer has nothing pending for the next. Resetting the
 * demo is the clean way to get all 40 back.
 */
final readonly class ReleaseAbandoned
{
    public const int AFTER_MINUTES = 30;

    public function __construct(private DemoDoor $door) {}

    /** @return int how many people were freed */
    public function handle(): int
    {
        if ($this->door->enter() === null) {
            return 0;
        }

        $released = DoorAssignment::query()->where('claimed_at', '<', now()->subMinutes(self::AFTER_MINUTES))->toBase()->delete();
        Log::info('demo.door_released', ['released' => $released]);

        return $released;
    }
}
