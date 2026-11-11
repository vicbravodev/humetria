<?php

declare(strict_types=1);

namespace Database\Factories\Engagement;

use App\Models\User;
use App\Modules\Engagement\Enums\PulseFrequency;
use App\Modules\Engagement\Models\PulseSchedule;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An active monthly schedule, on the 15th, open for a week.
 *
 * @extends Factory<PulseSchedule>
 */
final class PulseScheduleFactory extends Factory
{
    protected $model = PulseSchedule::class;

    /**
     * @return array<model-property<PulseSchedule>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'frequency' => PulseFrequency::Monthly,
            'day_of_month' => 15,
            'open_days' => 7,
            'active' => true,
            'next_run_on' => now()->addDays(5)->toDateString(),
            'created_by' => User::factory(),
        ];
    }
}
