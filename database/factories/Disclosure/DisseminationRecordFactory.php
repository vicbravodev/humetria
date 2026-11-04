<?php

declare(strict_types=1);

namespace Database\Factories\Disclosure;

use App\Models\User;
use App\Modules\Disclosure\Enums\Medium;
use App\Modules\Disclosure\Models\DisseminationRecord;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A poster that covered the policy (inciso a) in a work center, put up on a past day.
 *
 * @extends Factory<DisseminationRecord>
 */
final class DisseminationRecordFactory extends Factory
{
    protected $model = DisseminationRecord::class;

    /**
     * @return array<model-property<DisseminationRecord>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'medium' => Medium::Poster,
            'covered_items' => ['a'],
            'done_on' => '2026-11-01',
            'note' => null,
            'recorded_by' => User::factory(),
        ];
    }

    /**
     * @param  list<string>  $items
     */
    public function covering(array $items): self
    {
        return $this->state(['covered_items' => $items]);
    }
}
