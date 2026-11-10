<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentAllocation;
use App\Modules\Billing\Models\Statement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
final class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    /**
     * @return array<model-property<PaymentAllocation>, mixed>
     */
    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'statement_id' => Statement::factory()->issued(),
            'amount_cents' => 100_000,
        ];
    }
}
