<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Teleworker;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * An aspirant who teleworks half the time, an employee of the work center they are registered in.
 *
 * @extends Factory<Teleworker>
 */
final class TeleworkerFactory extends Factory
{
    protected $model = Teleworker::class;

    /**
     * @return array<model-property<Teleworker>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'work_center_id' => fn (array $attributes) => $this->workCenter($attributes),
            'status' => TeleworkerStatus::Aspirant,
            'telework_percentage' => 50,
        ];
    }

    public function active(): self
    {
        return $this->state(['status' => TeleworkerStatus::Active, 'started_at' => now()]);
    }

    /**
     * The work center of the employee, read straight from the table: the employee is another module's.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    private function workCenter(array $attributes): mixed
    {
        return DB::table('employees')->where('id', $attributes['employee_id'])->value('work_center_id');
    }
}
