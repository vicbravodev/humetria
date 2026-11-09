<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Modules\Attendance\Models\KioskDevice;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A QR screen in use, of a token nobody knows.
 *
 * @extends Factory<KioskDevice>
 */
final class KioskDeviceFactory extends Factory
{
    protected $model = KioskDevice::class;

    /**
     * @return array<model-property<KioskDevice>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->createOne(['tenant_id' => $attributes['tenant_id']])->id,
            'token_hash' => KioskDevice::hashToken(Str::random(48)),
        ];
    }
}
