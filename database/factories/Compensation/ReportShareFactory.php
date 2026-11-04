<?php

declare(strict_types=1);

namespace Database\Factories\Compensation;

use App\Models\User;
use App\Modules\Compensation\Models\ReportShare;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * A link to the report of a scenario, open for the next 24 hours, of the same company as the scenario.
 *
 * @extends Factory<ReportShare>
 */
final class ReportShareFactory extends Factory
{
    /** Named here so the factory also works when used on its own, as other modules' factories do. */
    protected $model = ReportShare::class;

    /**
     * @return array<model-property<ReportShare>, mixed>
     */
    public function definition(): array
    {
        return [
            'scenario_id' => ScenarioFactory::new(),
            'tenant_id' => function (array $attributes): string {
                $value = DB::table('scenarios')->where('id', $attributes['scenario_id'])->value('tenant_id');

                return is_string($value) ? $value : throw new InvalidArgumentException('The scenario must exist.');
            },
            'token_hash' => fn (): string => ReportShare::hashToken(Str::random(64)),
            'created_at' => now(),
            'expires_at' => now()->addDay(),
            'created_by' => User::factory(),
        ];
    }
}
