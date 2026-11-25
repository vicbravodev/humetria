<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Jobs\DeliverPush;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Support\Facades\Log;

/**
 * «Tiene un aviso en Humetría»: sends a notice to each phone of a person of the current company. It has no content to give: it
 * takes the person and nothing else, and what the phone shows is fixed in the app. Nothing leaves the demo company.
 */
final readonly class PushNotifier
{
    public function __construct(private CurrentTenant $tenant, private DemoCompanies $demo) {}

    /** @throws \App\Modules\Tenancy\Exceptions\NoCurrentTenant outside a company */
    public function notify(string $employeeId): void
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
            dispatch(new DeliverPush($tenantId, $subscription->id));
        }
    }
}
