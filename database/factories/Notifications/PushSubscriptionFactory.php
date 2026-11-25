<?php

declare(strict_types=1);

namespace Database\Factories\Notifications;

use App\Modules\Notifications\Models\PushSubscription;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * The subscription of a phone nobody holds (Chrome's push service by default). Nothing is encrypted, so the keys are random
 * base64url of the size a browser gives (65 and 16 bytes), not vectors of RFC 8291.
 *
 * @extends Factory<PushSubscription>
 */
final class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    /**
     * @return array<model-property<PushSubscription>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::uuid(),
            'p256dh' => Str::random(87),
            'auth' => Str::random(22),
        ];
    }

    /** A phone on Firefox's push service. */
    public function mozilla(): static
    {
        return $this->state(['endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/'.Str::uuid()]);
    }

    /** A phone on Apple's push service (Safari, the app on the home screen). */
    public function apple(): static
    {
        return $this->state(['endpoint' => 'https://web.push.apple.com/'.Str::uuid()]);
    }

    /** One whose last notice arrived. */
    public function succeeded(): static
    {
        return $this->state(['last_success_at' => now()]);
    }
}
