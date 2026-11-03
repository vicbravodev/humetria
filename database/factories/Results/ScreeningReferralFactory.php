<?php

declare(strict_types=1);

namespace Database\Factories\Results;

use App\Models\User;
use App\Modules\Results\Enums\ReferralStatus;
use App\Modules\Results\Models\ScreeningReferral;
use Database\Factories\Responses\TraumaScreeningFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * A person referred to the IMSS last week, whose Guía I (made with it) requires a clinical assessment.
 *
 * @extends Factory<ScreeningReferral>
 */
final class ScreeningReferralFactory extends Factory
{
    protected $model = ScreeningReferral::class;

    /**
     * @return array<model-property<ScreeningReferral>, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => fn (): string => TraumaScreeningFactory::new()->state(['requires_clinical_assessment' => true])->createOne()->campaign_id,
            'tenant_id' => fn (array $attributes): string => $this->column('campaigns', 'id', $attributes['campaign_id'], 'tenant_id'),
            'employee_id' => fn (array $attributes): string => $this->column('trauma_screenings', 'campaign_id', $attributes['campaign_id'], 'employee_id'),
            'status' => ReferralStatus::Referred,
            'referred_on' => now()->subWeek()->toDateString(),
            'institution' => 'IMSS, Unidad de Medicina Familiar 7',
            'updated_by' => User::factory(),
        ];
    }

    /** A column of another module's table, read from it: its model is not this module's to use. */
    private function column(string $table, string $key, mixed $value, string $column): string
    {
        $found = DB::table($table)->where($key, $value)->value($column);

        return is_string($found) ? $found : throw new InvalidArgumentException("The {$table} row must exist.");
    }
}
