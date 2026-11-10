<?php

declare(strict_types=1);

namespace Database\Factories\Engagement;

use App\Modules\Engagement\Models\Response;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A response with no segment (the whole company) that answered one question.
 *
 * @extends Factory<Response>
 */
final class ResponseFactory extends Factory
{
    protected $model = Response::class;

    /**
     * @return array<model-property<Response>, mixed>
     */
    public function definition(): array
    {
        return [
            'work_center_id' => null,
            'area_id' => null,
            'tenure_band' => null,
            'manager_group_id' => null,
            'answers' => ['clarity.goals' => 4],
            'minutes' => null,
        ];
    }
}
