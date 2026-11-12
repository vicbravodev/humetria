<?php

declare(strict_types=1);

namespace Database\Factories\Tenancy;

use App\Modules\Tenancy\Enums\TenantRestriction;
use App\Modules\Tenancy\Enums\TenantType;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
final class TenantFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = Tenant::class;

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
            'type' => TenantType::Company,
        ];
    }

    public function union(): self
    {
        return $this->state(['type' => TenantType::Union]);
    }

    public function inTrial(CarbonInterface $startedAt): self
    {
        return $this->state(['trial_started_at' => $startedAt]);
    }

    public function activeUntil(CarbonInterface $until): self
    {
        return $this->state(['active_until' => $until]);
    }

    public function readOnly(string $reason = 'Solo lectura'): self
    {
        return $this->restricted(TenantRestriction::ReadOnly, $reason);
    }

    public function suspended(string $reason = 'Suspendida'): self
    {
        return $this->restricted(TenantRestriction::Suspended, $reason);
    }

    public function archived(string $reason = 'Archivada'): self
    {
        return $this->restricted(TenantRestriction::Archived, $reason);
    }

    private function restricted(TenantRestriction $restriction, string $reason): self
    {
        return $this->state(['restriction' => $restriction, 'restriction_reason' => $reason, 'restricted_at' => now()]);
    }
}
