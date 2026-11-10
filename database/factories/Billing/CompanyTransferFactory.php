<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\User;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\CompanyTransfer;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyTransfer>
 */
final class CompanyTransferFactory extends Factory
{
    protected $model = CompanyTransfer::class;

    /**
     * @return array<model-property<CompanyTransfer>, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => TenantFactory::new(),
            'from_account_id' => BillingAccount::factory(),
            'to_account_id' => BillingAccount::factory(),
            'effective_on' => '2027-01-01',
            'reason' => 'El cliente contrata directo',
            'created_by' => User::factory(),
        ];
    }
}
