<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Enums\EscalationReason;
use App\Modules\Leave\Enums\RequestStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use Database\Factories\Organization\EmployeeFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Vacations a person of its own company asked for on Monday 2 November 2026: from Monday 9 to Friday 20 November, 9
 * working days (the 16th is a holiday), waiting for an answer, with the days to cover it.
 *
 * @extends Factory<LeaveRequest>
 */
final class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    /**
     * @return array<model-property<LeaveRequest>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'employee_id' => fn (array $attributes) => EmployeeFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'starts_on' => '2026-11-09',
            'ends_on' => '2026-11-20',
            'working_days' => 9,
            'status' => RequestStatus::Requested,
            'had_balance' => true,
            'allocation' => [],
            'requested_on' => '2026-11-02',
        ];
    }

    /** A leave of a type of the company, which uses no balance. */
    public function ofType(LeaveType $type): self
    {
        return $this->state(['tenant_id' => $type->tenant_id, 'leave_type_id' => $type->id, 'had_balance' => null]);
    }

    public function approved(): self
    {
        return $this->state(['status' => RequestStatus::Approved, 'decided_at' => '2026-11-03 18:00:00']);
    }

    public function taken(): self
    {
        return $this->state(['status' => RequestStatus::Taken, 'decided_at' => '2026-11-03 18:00:00']);
    }

    public function rejected(): self
    {
        return $this->state(['status' => RequestStatus::Rejected, 'decided_at' => '2026-11-03 18:00:00', 'rejection_reason' => 'Cierre de mes']);
    }

    public function cancelled(): self
    {
        return $this->state(['status' => RequestStatus::Cancelled, 'cancelled_at' => '2026-11-03 18:00:00']);
    }

    /** It reached RH because the manager did not answer in time. */
    public function escalated(): self
    {
        return $this->state(['escalated_at' => '2026-11-06 06:40:00', 'escalation_reason' => EscalationReason::Timeout]);
    }
}
