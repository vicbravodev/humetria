<?php

declare(strict_types=1);

namespace Database\Factories\Announcements;

use App\Modules\Announcements\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A published announcement to every company, running from an hour ago for a day.
 *
 * @extends Factory<Announcement>
 */
final class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<model-property<Announcement>, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => 'news',
            'audience' => 'all',
            'plan' => null,
            'title' => 'Nueva plantilla de reporte STPS',
            'message' => 'Ya puedes descargar el reporte con el formato nuevo de la STPS.',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'published_at' => now()->subHour(),
        ];
    }

    public function forPlan(string $plan): self
    {
        return $this->state(['audience' => 'plan', 'plan' => $plan]);
    }
}
