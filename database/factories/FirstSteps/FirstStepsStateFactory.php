<?php

declare(strict_types=1);

namespace Database\Factories\FirstSteps;

use App\Models\User;
use App\Modules\FirstSteps\Models\FirstStepsState;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A person's place in the guided tour of a company; by default, untouched.
 *
 * @extends Factory<FirstStepsState>
 */
final class FirstStepsStateFactory extends Factory
{
    protected $model = FirstStepsState::class;

    /**
     * @return array<model-property<FirstStepsState>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'user_id' => User::factory(),
        ];
    }

    public function welcomed(): self
    {
        return $this->state(['welcomed_at' => now()]);
    }

    public function hidden(): self
    {
        return $this->welcomed()->state(['hidden_at' => now()]);
    }

    public function completed(): self
    {
        return $this->welcomed()->state(['completed_at' => now()]);
    }
}
