<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Models\DoorSession;
use App\Modules\Demos\Support\DemoDoor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LogicException;

/**
 * Opens the door of the demo for a while, closing any still open: a new random code, of which only the hash is kept. The
 * address with the code exists only in what this returns, for the room to show as a QR.
 */
final readonly class OpenDoor
{
    public function __construct(private DemoDoor $door, private CloseDoor $close) {}

    /**
     * @return array{url: string}
     */
    public function handle(int $minutes = 120): array
    {
        $tenantId = $this->door->enter() ?? throw new LogicException('There is no demo company to open the door of.');
        // 24 characters out of 62: about 143 random bits.
        $code = Str::random(24);

        $session = DB::transaction(function () use ($tenantId, $code, $minutes): DoorSession {
            $this->close->handle();

            return DoorSession::query()->create(['tenant_id' => $tenantId, 'code_hash' => hash('sha256', $code), 'expires_at' => now()->addMinutes($minutes)]);
        });

        Log::info('demo.door_opened', ['session_id' => $session->id, 'minutes' => $minutes]);

        return ['url' => route('demo.door', ['code' => $code])];
    }
}
