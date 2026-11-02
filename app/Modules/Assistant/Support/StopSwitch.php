<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Support;

use Illuminate\Support\Facades\Cache;

/**
 * The «Detener» button, as the server hears it: while a person has one answer running, pressing stop leaves a mark that
 * the agent reads before each step, so it asks the provider for nothing more. One answer per person at a time; the mark
 * lapses by itself and is cleared when the next message starts.
 */
final class StopSwitch
{
    private const int SECONDS = 300;

    public function request(int $participantId): void
    {
        Cache::put($this->key($participantId), true, self::SECONDS);
    }

    public function requested(int $participantId): bool
    {
        return Cache::has($this->key($participantId));
    }

    public function clear(int $participantId): void
    {
        Cache::forget($this->key($participantId));
    }

    private function key(int $participantId): string
    {
        return "assistant:stop:{$participantId}";
    }
}
