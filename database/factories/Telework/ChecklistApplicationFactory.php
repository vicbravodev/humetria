<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\Workplace;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * A Section I checklist waiting to be answered, of a published list of the teleworker's work center.
 *
 * @extends Factory<ChecklistApplication>
 */
final class ChecklistApplicationFactory extends Factory
{
    protected $model = ChecklistApplication::class;

    /**
     * @return array<model-property<ChecklistApplication>, mixed>
     */
    public function definition(): array
    {
        return [
            'workplace_id' => WorkplaceFactory::new(),
            'tenant_id' => fn (array $attributes) => $this->workplace($attributes)->tenant_id,
            'checklist_id' => fn (array $attributes) => ChecklistFactory::new()->published()->state([
                'tenant_id' => $attributes['tenant_id'],
                'work_center_id' => $this->workplace($attributes)->teleworker()->withoutGlobalScopes()->firstOrFail()->work_center_id,
            ]),
            'section' => Section::One,
            'requested_at' => now(),
        ];
    }

    public function submitted(): self
    {
        return $this->state(['submitted_at' => now()]);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function workplace(array $attributes): Workplace
    {
        $id = $attributes['workplace_id'];

        return Workplace::query()->withoutGlobalScopes()->findOrFail(is_string($id) ? $id : throw new InvalidArgumentException('A workplace id is needed.'));
    }
}
