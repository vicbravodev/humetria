<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Actions;

use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * A person's phone allows the notices: keeps its subscription in the current company, one row per address. A shared phone is best
 * effort: the address goes to whoever subscribed last, and the one before stops getting notices on it until they subscribe again.
 * A person keeps up to five phones: past that, the one that subscribed longest ago goes.
 */
final readonly class StoreSubscription
{
    public const int MAX_PER_PERSON = 5; // @pest-mutate-ignore: IncrementInteger,DecrementInteger

    public function __construct(private CurrentTenant $tenant) {}

    public function handle(string $employeeId, string $endpoint, string $p256dh, string $auth): PushSubscription
    {
        return DB::transaction(function () use ($employeeId, $endpoint, $p256dh, $auth): PushSubscription {
            $replaced = PushSubscription::query()->where('endpoint', $endpoint)->exists();

            // One statement: two requests with the same address meet at the unique key, not in a race of select and insert.
            PushSubscription::query()->upsert(
                [['id' => (string) Str::uuid(), 'tenant_id' => $this->tenant->id(), 'employee_id' => $employeeId, 'endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth]],
                ['tenant_id', 'endpoint'],
                ['employee_id', 'p256dh', 'auth'],
            );
            $saved = PushSubscription::query()->where('endpoint', $endpoint)->sole();

            // ponytail: no lock per person; two phones subscribing at the very same moment may leave six until the next one prunes.
            $oldest = PushSubscription::query()->where('employee_id', $employeeId)->whereKeyNot($saved->id)
                ->orderByDesc('updated_at')->orderByDesc('id')->skip(self::MAX_PER_PERSON - 1)->take(PHP_INT_MAX)->pluck('id');
            PushSubscription::query()->whereKey($oldest)->delete();

            Log::info('push.subscribed', ['subscription_id' => $saved->id, 'replaced' => $replaced]);

            return $saved;
        });
    }
}
