<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\User;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<model-property<Payment>, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_account_id' => BillingAccount::factory(),
            'amount_cents' => 568_400,
            'paid_on' => '2026-12-05',
            'reference' => 'SPEI-'.fake()->unique()->numerify('########'),
            'note' => null,
            'recorded_by' => User::factory(),
        ];
    }
}
