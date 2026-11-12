<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\User;
use App\Modules\Billing\Enums\ChargeKind;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\SubscriptionCharge;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SubscriptionCharge>
 */
final class SubscriptionChargeFactory extends Factory
{
    protected $model = SubscriptionCharge::class;

    /**
     * @return array<model-property<SubscriptionCharge>, mixed>
     */
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'tenant_id' => TenantFactory::new(),
            'kind' => ChargeKind::OneTime,
            'description' => 'Implementación',
            'scope' => 'Una empresa',
            'amount_cents' => 500_000,
            'charge_on' => '2026-12-01',
            'ends_on' => null,
            'approved_by' => User::factory(),
        ];
    }

    public function recurring(): self
    {
        return $this->state(['kind' => ChargeKind::Recurring, 'description' => 'Acompañamiento']);
    }
}
