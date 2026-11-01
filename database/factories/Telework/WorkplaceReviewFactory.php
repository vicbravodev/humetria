<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Models\User;
use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\ReviewMethod;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Models\WorkplaceReview;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * A remote check that validated the workplace, decided now.
 *
 * @extends Factory<WorkplaceReview>
 */
final class WorkplaceReviewFactory extends Factory
{
    protected $model = WorkplaceReview::class;

    /**
     * @return array<model-property<WorkplaceReview>, mixed>
     */
    public function definition(): array
    {
        return [
            'workplace_id' => WorkplaceFactory::new(),
            'tenant_id' => fn (array $attributes) => $this->tenantOf($attributes),
            'method' => ReviewMethod::RemoteCheck,
            'decision' => ReviewDecision::Valid,
            'notes' => '',
            'decided_by' => User::factory(),
            'decided_at' => now(),
        ];
    }

    public function notValid(): self
    {
        return $this->state(['decision' => ReviewDecision::NotValid, 'notes' => 'El espacio no es seguro.']);
    }

    public function withPlan(): self
    {
        return $this->state(['decision' => ReviewDecision::ValidWithPlan, 'action_plan' => 'Cambiar la silla.', 'plan_due_on' => now()->addMonth()->toDateString()]);
    }

    public function decidedAt(string $moment): self
    {
        return $this->state(['decided_at' => $moment]);
    }

    /**
     * @param  array<array-key, mixed>  $attributes
     */
    private function tenantOf(array $attributes): string
    {
        $id = $attributes['workplace_id'];

        return Workplace::query()->withoutGlobalScopes()->findOrFail(is_string($id) ? $id : throw new InvalidArgumentException('A workplace id is needed.'))->tenant_id;
    }
}
