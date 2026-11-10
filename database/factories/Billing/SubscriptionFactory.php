<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Enums\Plan;
use App\Modules\Billing\Enums\Profile;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Subscription;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * @return array<model-property<Subscription>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'plan' => Plan::Cumplimiento,
            'profile' => Profile::Direct,
            'catalog_version' => 'v1',
            'status' => SubscriptionStatus::Active,
            'seats' => 100,
            'centers' => 2,
            'starts_on' => '2026-12-01',
            'renews_on' => '2027-01-01',
        ];
    }

    public function plan(Plan $plan, Profile $profile = Profile::Direct): self
    {
        return $this->state(['plan' => $plan, 'profile' => $profile]);
    }

    public function status(SubscriptionStatus $status): self
    {
        return $this->state(['status' => $status]);
    }
}
