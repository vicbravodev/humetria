<?php

declare(strict_types=1);

namespace Database\Factories\Telework;

use App\Modules\Telework\Enums\NoticeKind;
use App\Modules\Telework\Models\WorkerNotice;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A work risk notice written on 27 Feb 2026 about something that happened on the 25th, not reviewed yet.
 *
 * @extends Factory<WorkerNotice>
 */
final class WorkerNoticeFactory extends Factory
{
    protected $model = WorkerNotice::class;

    /**
     * @return array<model-property<WorkerNotice>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'teleworker_id' => fn (array $attributes) => TeleworkerFactory::new()->active()->state(['tenant_id' => $attributes['tenant_id']]),
            'kind' => NoticeKind::WorkRisk,
            'written_on' => '2026-02-27',
            'occurred_on' => '2026-02-25',
            'description' => 'Me lastimé la espalda al levantar una caja.',
            'submitted_at' => '2026-02-27 18:00:00',
        ];
    }
}
