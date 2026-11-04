<?php

declare(strict_types=1);

namespace Database\Factories\Disclosure;

use App\Models\User;
use App\Modules\Disclosure\Models\ResultPublication;
use Database\Factories\Campaigns\CampaignFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * An active publication of a closed evaluation, with its center, both of the same company.
 *
 * @extends Factory<ResultPublication>
 */
final class ResultPublicationFactory extends Factory
{
    protected $model = ResultPublication::class;

    /**
     * @return array<model-property<ResultPublication>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'campaign_id' => fn (array $attributes) => CampaignFactory::new()->closed()->state(['tenant_id' => $attributes['tenant_id']]),
            'work_center_id' => fn (array $attributes) => $this->centerOf($attributes['campaign_id']),
            'show_measures' => false,
            'published_at' => now(),
            'published_by' => User::factory(),
            'withdrawn_at' => null,
            'withdrawn_by' => null,
        ];
    }

    private function centerOf(mixed $campaignId): mixed
    {
        return DB::table('campaigns')->where('id', $campaignId)->value('work_center_id');
    }
}
