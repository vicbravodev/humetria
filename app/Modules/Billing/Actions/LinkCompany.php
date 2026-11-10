<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Exceptions\CompanyAlreadyHasPayer;
use App\Modules\Billing\Models\AccountCompany;
use App\Modules\Billing\Models\BillingAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class LinkCompany
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Makes the account the payer of the company from a day. A company has one current payer: if it has one already, even this
     * account, nothing is written.
     *
     * @throws CompanyAlreadyHasPayer
     */
    public function handle(BillingAccount $account, string $tenantId, CarbonImmutable $startsOn): AccountCompany
    {
        $current = AccountCompany::query()->current()->where('tenant_id', $tenantId)->first();

        if ($current !== null) {
            throw CompanyAlreadyHasPayer::paidBy($this->payerName($current));
        }

        try {
            return DB::transaction(function () use ($account, $tenantId, $startsOn): AccountCompany {
                $link = AccountCompany::query()->create([
                    'billing_account_id' => $account->id,
                    'tenant_id' => $tenantId,
                    'starts_on' => $startsOn,
                ]);

                Log::info('billing.company_linked', ['account_id' => $account->id, 'tenant_id' => $tenantId]);
                $this->recordAudit->handle('billing.company_linked', $link, ['account_id' => $account->id, 'tenant_id' => $tenantId, 'starts_on' => $startsOn->toDateString()]);

                return $link;
            });
        } catch (UniqueConstraintViolationException) {
            // Two consoles linked it at once: the index let one through; the other is told who got there first.
            $winner = AccountCompany::query()->current()->where('tenant_id', $tenantId)->firstOrFail();

            throw CompanyAlreadyHasPayer::paidBy($this->payerName($winner));
        }
    }

    private function payerName(AccountCompany $link): string
    {
        return BillingAccount::query()->findOrFail($link->billing_account_id)->legal_name;
    }
}
