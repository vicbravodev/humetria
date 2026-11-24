<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\TenantChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantChannel>
 */
final class TenantChannelFactory extends Factory
{
    protected $model = TenantChannel::class;

    /**
     * @return array<model-property<TenantChannel>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'channel' => 'mail',
            'enabled' => true,
            'preference' => 1,
            'monthly_cap' => null,
            'consent_wording_version' => null,
            'enabled_by' => null,
            'enabled_at' => null,
        ];
    }

    /** WhatsApp on, with the words of the current version accepted now. */
    public function whatsAppEnabled(): self
    {
        return $this->state([
            'channel' => 'whatsapp',
            'enabled' => true,
            'preference' => 2,
            'consent_wording_version' => config()->string('whatsapp.consent.version'),
            'enabled_at' => now(),
        ]);
    }

    public function mailDisabled(): self
    {
        return $this->state(['channel' => 'mail', 'enabled' => false, 'preference' => 1]);
    }
}
