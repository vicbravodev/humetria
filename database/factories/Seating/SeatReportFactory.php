<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Modules\Seating\Enums\ReportStatus;
use App\Modules\Seating\Models\SeatReport;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open report from the portal.
 *
 * @extends Factory<SeatReport>
 */
final class SeatReportFactory extends Factory
{
    protected $model = SeatReport::class;

    /**
     * @return array<model-property<SeatReport>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'seat_id' => null,
            'position_id' => null,
            'description' => 'Una silla tiene el respaldo flojo.',
            'status' => ReportStatus::Open,
            'resolution' => null,
            'resolved_at' => null,
            'reported_via_portal' => true,
        ];
    }

    public function resolved(): self
    {
        return $this->state(['status' => ReportStatus::Resolved, 'resolution' => 'Se reparó.', 'resolved_at' => now()]);
    }
}
