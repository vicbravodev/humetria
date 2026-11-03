<?php

declare(strict_types=1);

namespace Database\Factories\Assistant;

use App\Modules\Assistant\Models\AiUsage;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * One message to Humi that used some tokens.
 *
 * @extends Factory<AiUsage>
 */
final class AiUsageFactory extends Factory
{
    protected $model = AiUsage::class;

    /**
     * @return array<model-property<AiUsage>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'agent' => 'HumiAssistant',
            'input_tokens' => 120,
            'output_tokens' => 80,
            'tools' => [],
            'latency_ms' => 900,
            'created_at' => now(),
        ];
    }
}
