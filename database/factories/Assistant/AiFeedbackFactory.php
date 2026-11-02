<?php

declare(strict_types=1);

namespace Database\Factories\Assistant;

use App\Modules\Assistant\Models\AiFeedback;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A thumb up on an answer of Humi.
 *
 * @extends Factory<AiFeedback>
 */
final class AiFeedbackFactory extends Factory
{
    protected $model = AiFeedback::class;

    /**
     * @return array<model-property<AiFeedback>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'conversation_id' => (string) Str::uuid(),
            'message_id' => (string) Str::uuid(),
            'rating' => 'up',
            'created_at' => now(),
        ];
    }
}
