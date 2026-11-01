<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Enums\ChecklistStatus;
use App\Modules\Telework\Enums\Section;
use App\Modules\Telework\Models\Checklist;
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
            'checklist_id' => fn (array $attributes) => $this->publishedChecklist($attributes),
            'section' => Section::One,
            'requested_at' => now(),
        ];
    }

    public function submitted(): self
    {
        return $this->state(['submitted_at' => now()]);
    }

    /**
     * The published list of the teleworker's work center, made when the center has none yet.
     *
     * @param  array<array-key, mixed>  $attributes
     */
    private function publishedChecklist(array $attributes): Checklist
    {
        $workCenterId = $this->workplace($attributes)->teleworker()->withoutGlobalScopes()->firstOrFail()->work_center_id;

        return Checklist::query()->withoutGlobalScopes()->where('work_center_id', $workCenterId)->where('status', ChecklistStatus::Published)->first()
            ?? ChecklistFactory::new()->published()->createOne(['tenant_id' => $attributes['tenant_id'], 'work_center_id' => $workCenterId]);
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
