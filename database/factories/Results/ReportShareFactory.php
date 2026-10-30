<?php

declare(strict_types=1);

namespace Database\Factories\Results;

use App\Models\User;
use App\Modules\Results\Models\ReportShare;
use Database\Factories\Campaigns\CampaignFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A link to a closed evaluation's report, open for the next 24 hours.
 *
 * @extends Factory<ReportShare>
 */
final class ReportShareFactory extends Factory
{
    protected $model = ReportShare::class;

    /**
     * @return array<model-property<ReportShare>, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => CampaignFactory::new()->closed(),
            'tenant_id' => function (array $attributes): string {
                $value = DB::table('campaigns')->where('id', $attributes['campaign_id'])->value('tenant_id');

                return is_string($value) ? $value : throw new InvalidArgumentException('The campaign must exist.');
            },
            'token_hash' => fn (): string => ReportShare::hashToken(Str::random(64)),
            'created_at' => now(),
            'expires_at' => now()->addDay(),
            'created_by' => User::factory(),
        ];
    }
}
