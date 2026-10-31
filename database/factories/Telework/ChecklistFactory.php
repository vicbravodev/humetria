<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Models\Checklist;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A draft of a work center's checklist, without questions.
 *
 * @extends Factory<Checklist>
 */
final class ChecklistFactory extends Factory
{
    protected $model = Checklist::class;

    /**
     * @return array<model-property<Checklist>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'version' => 1,
            'status' => ChecklistStatus::Draft,
        ];
    }

    public function published(): self
    {
        return $this->state(['status' => ChecklistStatus::Published, 'published_at' => now()]);
    }
}
