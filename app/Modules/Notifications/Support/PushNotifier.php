<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Enums\PushKind;
use App\Modules\Notifications\Jobs\DeliverPush;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Support\Facades\Log;

/**
 * Sends a notice to each phone of a person of the current company. It takes the person and the kind of notice, a word from a closed
 * list: what the phone shows for each kind is fixed in the app, and there is no way to pass anything else. Nothing leaves the demo company.
 */
final readonly class PushNotifier
{
    public function __construct(private CurrentTenant $tenant, private DemoCompanies $demo) {}

    /** @throws \App\Modules\Tenancy\Exceptions\NoCurrentTenant outside a company */
    public function notify(string $employeeId, PushKind $kind): void
    {
        $tenantId = $this->tenant->id();

        if (VapidConfig::fromConfig() === null) {
            return;
        }

        if ($this->demo->isDemo($tenantId)) {
            Log::debug('push.demo_blocked');

            return;
        }

        foreach (PushSubscription::query()->where('employee_id', $employeeId)->get() as $subscription) {
            dispatch(new DeliverPush($tenantId, $subscription->id, $kind));
        }
    }
}
