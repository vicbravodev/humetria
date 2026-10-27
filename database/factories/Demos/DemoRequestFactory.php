<?php

declare(strict_types=1);

namespace Database\Factories\Demos;

use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Workforce;
use App\Modules\Demos\Models\DemoRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DemoRequest>
 */
final class DemoRequestFactory extends Factory
{
    /**
     * @return array<model-property<DemoRequest>, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'whatsapp' => '+52'.fake()->numerify('55########'),
            'company' => fake()->company(),
            'profile' => fake()->randomElement(DemoProfile::cases()),
            'workforce' => fake()->randomElement(Workforce::cases()),
            'message' => null,
        ];
    }
}
