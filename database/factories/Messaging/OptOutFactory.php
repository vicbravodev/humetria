<?php

declare(strict_types=1);

namespace Database\Factories\Messaging;

use App\Modules\Messaging\Models\OptOut;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OptOut>
 */
final class OptOutFactory extends Factory
{
    protected $model = OptOut::class;

    /**
     * @return array<model-property<OptOut>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'recipient_hash' => hash('sha256', Str::random(16)),
            'key_version' => 1,
            'source' => 'stop',
        ];
    }

    /** Every company. */
    public function global(): self
    {
        return $this->state(['tenant_id' => null]);
    }
}
