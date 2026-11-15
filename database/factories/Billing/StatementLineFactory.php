<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Models\Statement;
use App\Modules\Billing\Models\StatementLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatementLine>
 */
final class StatementLineFactory extends Factory
{
    protected $model = StatementLine::class;

    /**
     * @return array<model-property<StatementLine>, mixed>
     */
    public function definition(): array
    {
        return [
            'statement_id' => Statement::factory(),
            'position' => 1,
            'code' => 'plan',
            'label' => 'Cumplimiento',
            'amount_cents' => 490_000,
            'explanation' => '100 personas × $49 = $4,900',
            'charge_id' => null,
        ];
    }
}
