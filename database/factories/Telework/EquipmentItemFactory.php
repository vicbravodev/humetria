<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Enums\EquipmentKind;
use App\Modules\Telework\Enums\EquipmentStatus;
use App\Modules\Telework\Models\EquipmentItem;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An ergonomic chair delivered on 20 Feb 2026, waiting for the person's receipt.
 *
 * @extends Factory<EquipmentItem>
 */
final class EquipmentItemFactory extends Factory
{
    protected $model = EquipmentItem::class;

    /**
     * @return array<model-property<EquipmentItem>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'teleworker_id' => fn (array $attributes) => TeleworkerFactory::new()->active()->state(['tenant_id' => $attributes['tenant_id']]),
            'kind' => EquipmentKind::Chair,
            'description' => 'Silla ergonómica',
            'delivered_on' => '2026-02-20',
            'status' => EquipmentStatus::Pending,
            'registered_by' => User::factory(),
        ];
    }
}
