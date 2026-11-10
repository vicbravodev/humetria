<?php

declare(strict_types=1);

namespace Database\Factories\Engagement;

use App\Modules\Engagement\Enums\SummaryStatus;
use App\Modules\Engagement\Models\CommentSummary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A summary of the comments about the company that is ready, by default.
 *
 * @extends Factory<CommentSummary>
 */
final class CommentSummaryFactory extends Factory
{
    protected $model = CommentSummary::class;

    /**
     * @return array<model-property<CommentSummary>, mixed>
     */
    public function definition(): array
    {
        return [
            'manager_group_id' => null,
            'status' => SummaryStatus::Ready,
            'themes' => ['valued' => [['theme' => 'Buen trato entre compañeros', 'count' => 4]], 'change' => [['theme' => 'Más comunicación de la dirección', 'count' => 5]]],
            'generated_on' => now()->toDateString(),
        ];
    }

    public function status(SummaryStatus $status): self
    {
        return $this->state(['status' => $status, 'themes' => null, 'generated_on' => null]);
    }
}
