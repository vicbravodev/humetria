<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Modules\Attendance\Enums\OutOfZoneRule;
use App\Modules\Attendance\Models\AttendancePolicy;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A company's policy with the defaults.
 *
 * @extends Factory<AttendancePolicy>
 */
final class AttendancePolicyFactory extends Factory
{
    protected $model = AttendancePolicy::class;

    /**
     * @return array<model-property<AttendancePolicy>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'out_of_zone' => OutOfZoneRule::Block,
            'max_accuracy_m' => 100,
            'require_qr_onsite' => true,
            'trusted_may_use_browser' => true,
            'retention_months' => 12,
        ];
    }
}
