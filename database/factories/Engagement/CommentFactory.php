<?php

declare(strict_types=1);

namespace Database\Factories\Engagement;

use App\Modules\Engagement\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A comment on the open question about the company.
 *
 * @extends Factory<Comment>
 */
final class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<model-property<Comment>, mixed>
     */
    public function definition(): array
    {
        return [
            'item_key' => 'open.company',
            'comment_group_id' => null,
            'body' => fake()->sentence(),
        ];
    }
}
