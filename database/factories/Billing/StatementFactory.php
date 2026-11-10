<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Models\User;
use App\Modules\Billing\Enums\StatementStatus;
use App\Modules\Billing\Models\BillingAccount;
use App\Modules\Billing\Models\Statement;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Statement>
 */
final class StatementFactory extends Factory
{
    protected $model = Statement::class;

    /**
     * @return array<model-property<Statement>, mixed>
     */
    public function definition(): array
    {
        return [
            'billing_account_id' => BillingAccount::factory(),
            'tenant_id' => TenantFactory::new(),
            'adjusts_statement_id' => null,
            'period_start' => '2026-12-01',
            'period_end' => '2027-01-01',
            'status' => StatementStatus::Draft,
            'currency' => 'MXN',
            'catalog_version' => 'v1',
            'vat_percent' => 16,
            'subtotal_cents' => 490_000,
            'vat_cents' => 78_400,
            'total_cents' => 568_400,
            'snapshot' => [],
            'reason' => null,
            'issued_at' => null,
            'issued_by' => null,
            'created_by' => User::factory(),
        ];
    }

    /** Issued as it stands: with no lines, for tests that only need a document to apply payments to. */
    public function issued(): self
    {
        return $this->state(fn (): array => ['status' => StatementStatus::Issued, 'issued_at' => now(), 'issued_by' => User::factory()]);
    }
}
