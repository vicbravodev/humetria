<?php

declare(strict_types=1);

namespace Database\Factories\Seating;

use App\Modules\Seating\Enums\SeatCondition;
use App\Modules\Seating\Enums\SeatKind;
use App\Modules\Seating\Enums\SeatUse;
use App\Modules\Seating\Models\Seat;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * One ergonomic chair at a workstation, in good condition, without a photo.
 *
 * @extends Factory<Seat>
 */
final class SeatFactory extends Factory
{
    protected $model = Seat::class;

    /**
     * @return array<model-property<Seat>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'position_id' => null,
            'location' => 'Junto al puesto',
            'kind' => SeatKind::ErgonomicChair,
            'use' => SeatUse::Workstation,
            'quantity' => 1,
            'backrest' => true, 'height' => true, 'lumbar_support' => true, 'adjustable' => true, 'ergonomic' => true,
            'mobility' => true, 'stability' => true, 'armrests' => false, 'footrest' => false,
            'condition' => SeatCondition::Good,
            'signposted' => false,
            'photo_path' => null,
        ];
    }

    /** A seat of an area designated for the periodic rests. */
    public function restArea(): self
    {
        return $this->state(['use' => SeatUse::RestArea, 'location' => 'Comedor']);
    }
}
