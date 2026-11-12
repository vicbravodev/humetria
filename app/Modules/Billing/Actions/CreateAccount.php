<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Data\AccountData;
use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class CreateAccount
{
    public function __construct(private RecordAudit $recordAudit) {}

    public function handle(AccountData $data): BillingAccount
    {
        return DB::transaction(function () use ($data): BillingAccount {
            $account = BillingAccount::query()->create([
                'kind' => $data->kind,
                'legal_name' => $data->legalName,
                'rfc' => $data->rfc,
                'tax_regime' => $data->taxRegime,
                'postal_code' => $data->postalCode,
                'cfdi_use' => $data->cfdiUse,
                'contact_name' => $data->contactName,
                'contact_email' => $data->contactEmail,
                'contact_phone' => $data->contactPhone,
                'notes' => $data->notes,
            ]);

            // Only the id and the kind: the names, the RFC and the contact are a person's, and stay out of logs and audit.
            Log::info('billing.account_created', ['account_id' => $account->id, 'kind' => $data->kind->value]);
            $this->recordAudit->handle('billing.account_created', $account, ['kind' => $data->kind->value]);

            return $account;
        });
    }
}
