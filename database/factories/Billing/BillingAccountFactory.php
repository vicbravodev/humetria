<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Enums\AccountKind;
use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BillingAccount>
 */
final class BillingAccountFactory extends Factory
{
    protected $model = BillingAccount::class;

    /**
     * @return array<model-property<BillingAccount>, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => AccountKind::Direct,
            'legal_name' => fake()->company().' S.A. de C.V.',
            'rfc' => Str::upper(fake()->lexify('???').fake()->date('ymd').fake()->bothify('???')),
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
        ];
    }

    public function consultant(): self
    {
        return $this->state(['kind' => AccountKind::Consultant]);
    }
}
