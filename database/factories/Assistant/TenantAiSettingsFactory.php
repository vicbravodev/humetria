<?php

declare(strict_types=1);

namespace Database\Factories\Assistant;

use App\Modules\Assistant\Models\TenantAiSettings;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * The settings of Humi for a company: on, with the platform's default budget.
 *
 * @extends Factory<TenantAiSettings>
 */
final class TenantAiSettingsFactory extends Factory
{
    protected $model = TenantAiSettings::class;

    /**
     * @return array<model-property<TenantAiSettings>, mixed>
     */
    public function definition(): array
    {
        return ['tenant_id' => TenantFactory::new(), 'enabled' => true];
    }
}
