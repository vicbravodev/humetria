<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountCompany>
 */
final class AccountCompanyFactory extends Factory
{
    protected $model = AccountCompany::class;

    /**
     * @return array<model-property<AccountCompany>, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_account_id' => BillingAccount::factory(),
            'tenant_id' => TenantFactory::new(),
            'starts_on' => '2026-12-01',
            'ends_on' => null,
        ];
    }
}
