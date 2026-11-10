<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Models\LeaveHoliday;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A holiday of the company on Thursday 12 November 2026.
 *
 * @extends Factory<LeaveHoliday>
 */
final class LeaveHolidayFactory extends Factory
{
    protected $model = LeaveHoliday::class;

    /**
     * @return array<model-property<LeaveHoliday>, mixed>
     */
    public function definition(): array
    {
        return ['tenant_id' => TenantFactory::new(), 'date' => '2026-11-12', 'name' => 'Aniversario de la empresa'];
    }
}
