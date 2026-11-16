<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Models\DoorSession;
use App\Modules\Demos\Support\DemoDoor;
use Illuminate\Support\Facades\Log;

/** Closes the door of the demo: its code stops letting anyone in. The people already in keep their portals. */
final readonly class CloseDoor
{
    public function __construct(private DemoDoor $door) {}

    public function handle(): void
    {
        if ($this->door->enter() === null) {
            return;
        }

        foreach (DoorSession::query()->whereNull('closed_at')->get() as $session) {
            $session->forceFill(['closed_at' => now()])->save();
            Log::info('demo.door_closed', ['session_id' => $session->id]);
        }
    }
}
