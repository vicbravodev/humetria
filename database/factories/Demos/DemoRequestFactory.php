<?php

declare(strict_types=1);

namespace Database\Factories\Demos;

use App\Modules\Demos\Enums\DemoProfile;
use App\Modules\Demos\Enums\Tool;
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
            'tools' => fake()->randomElements(Tool::cases(), fake()->numberBetween(1, count(Tool::cases()))),
            'message' => null,
        ];
    }

    /** Came from a campaign of ours; the first contact is the same one. */
    public function fromCampaign(string $source = 'google', string $medium = 'cpc', string $campaign = 'nom-035'): self
    {
        return $this->state(fn (): array => [
            'utm_source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => $campaign,
            'first_utm_source' => $source,
            'first_utm_medium' => $medium,
            'first_utm_campaign' => $campaign,
        ]);
    }

    /** Came from another site, known only by its host; the first contact is the same one. */
    public function referredBy(string $host): self
    {
        return $this->state(fn (): array => ['referrer_host' => $host, 'first_referrer_host' => $host]);
    }
}
