<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Modules\Attendance\Enums\CorrectionStatus;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\CorrectionRequest;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A pending request of a person for a start they forgot, an hour ago.
 *
 * @extends Factory<CorrectionRequest>
 */
final class CorrectionRequestFactory extends Factory
{
    protected $model = CorrectionRequest::class;

    /**
     * @return array<model-property<CorrectionRequest>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'type' => MarkType::Start,
            'proposed_at' => now()->subHour(),
            'reason' => 'Olvidé marcar mi entrada.',
            'status' => CorrectionStatus::Pending,
        ];
    }
}
