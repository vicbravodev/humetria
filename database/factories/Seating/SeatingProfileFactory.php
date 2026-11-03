<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Models\User;
use App\Modules\Seating\Enums\Sector;
use App\Modules\Seating\Models\SeatingProfile;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A services center declared a month ago, with people working standing.
 *
 * @extends Factory<SeatingProfile>
 */
final class SeatingProfileFactory extends Factory
{
    protected $model = SeatingProfile::class;

    /**
     * @return array<model-property<SeatingProfile>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'sector' => Sector::Services,
            'no_standing_positions' => false,
            'declared_by' => User::factory(),
            'declared_on' => now()->subMonth()->toDateString(),
            'note' => null,
        ];
    }
}
