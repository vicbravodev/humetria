<?php

declare(strict_types=1);

namespace Database\Factories\Attendance;

use App\Models\User;
use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Models\MarkReview;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A verification of the mark of an accepted offline delivery, of the same company.
 *
 * @extends Factory<MarkReview>
 */
final class MarkReviewFactory extends Factory
{
    protected $model = MarkReview::class;

    /**
     * @return array<model-property<MarkReview>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'mark_id' => fn (array $attributes) => AttendanceOfflineMarkFactory::new()->accepted()->createOne(['tenant_id' => $attributes['tenant_id']])->mark_id,
            'decision' => ReviewDecision::Verified,
            'reviewer_id' => User::factory(),
        ];
    }

    public function incorrect(string $note = 'El quiosco del centro sí tenía luz.'): self
    {
        return $this->state(['decision' => ReviewDecision::Incorrect, 'note' => $note]);
    }
}
