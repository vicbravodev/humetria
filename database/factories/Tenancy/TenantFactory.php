<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    /**
     * @return array<model-property<Tenant>, mixed>
     */
    public function definition(): array
    {
        $tradeName = fake()->company();

        return [
            'legal_name' => fake()->company().' S.A. de C.V.',
            'trade_name' => $tradeName,
            'rfc' => Str::upper(fake()->lexify('???').fake()->date('ymd').fake()->bothify('???')),
            'slug' => Str::slug($tradeName).'-'.fake()->unique()->numberBetween(1, 99999),
        ];
    }

    public function inTrial(CarbonInterface $startedAt): self
    {
        return $this->state(['trial_started_at' => $startedAt]);
    }

    public function activeUntil(CarbonInterface $until): self
    {
        return $this->state(['active_until' => $until]);
    }

    public function deactivated(): self
    {
        return $this->state(['deactivated_at' => now()]);
    }
}
