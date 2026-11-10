<?php

declare(strict_types=1);

namespace Database\Factories\Leave;

use App\Modules\Leave\Enums\WorkWeek;
use App\Modules\Leave\Models\LeaveSetting;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A company that started using Vacaciones on 1 January 2026, counting Monday to Friday.
 *
 * @extends Factory<LeaveSetting>
 */
final class LeaveSettingFactory extends Factory
{
    protected $model = LeaveSetting::class;

    /**
     * @return array<model-property<LeaveSetting>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'tracking_since' => '2026-01-01',
            'statutory_work_week' => WorkWeek::MonFri,
        ];
    }
}
