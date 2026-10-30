<?php

declare(strict_types=1);

namespace Database\Factories\Campaigns;

use App\Models\User;
use App\Modules\Campaigns\Enums\CampaignKind;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Enums\Population;
use App\Modules\Campaigns\Models\Campaign;
use Database\Factories\Tenancy\TenantFactory;
use Database\Factories\Tenancy\WorkCenterFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A NOM-035 evaluation draft of a work center of 30 people (Guías I and II), by default.
 *
 * @extends Factory<Campaign>
 */
final class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    /**
     * @return array<model-property<Campaign>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'work_center_id' => fn (array $attributes) => WorkCenterFactory::new()->state(['tenant_id' => $attributes['tenant_id']]),
            'kind' => CampaignKind::Nom035Evaluation,
            'status' => CampaignStatus::Draft,
            'instruments' => ['nom035.guide-i', 'nom035.guide-ii'],
            'workers' => 30,
            'population' => Population::All,
            'created_by' => User::factory(),
        ];
    }

    public function active(?string $closesOn = null): self
    {
        return $this->state([
            'status' => CampaignStatus::Active,
            'launched_at' => now(),
            'closes_on' => $closesOn ?? now()->addDays(14)->toDateString(),
        ]);
    }

    public function closed(): self
    {
        return $this->active(now()->subDay()->toDateString())->state(['status' => CampaignStatus::Closed, 'closed_at' => now()]);
    }

    public function trauma(): self
    {
        return $this->active()->state([
            'kind' => CampaignKind::Nom035Trauma,
            'instruments' => ['nom035.guide-i'],
            'workers' => null,
            'population' => null,
        ]);
    }
}
