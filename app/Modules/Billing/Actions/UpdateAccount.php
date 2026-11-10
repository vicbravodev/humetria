<?php

declare(strict_types=1);

namespace App\Modules\Billing\Actions;

use App\Actions\RecordAudit;
use App\Modules\Billing\Data\AccountData;
use App\Modules\Billing\Models\BillingAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class UpdateAccount
{
    public function __construct(private RecordAudit $recordAudit) {}

    /**
     * Corrects the account's data. Its kind is not one of them: an account that pays for a consultant's companies cannot become a
     * direct one under its channel prices. Writes nothing when nothing changed.
     */
    public function handle(BillingAccount $account, AccountData $data): BillingAccount
    {
        return DB::transaction(function () use ($account, $data): BillingAccount {
            $account->fill([
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
            $fields = array_keys($account->getDirty());

            if ($fields === []) {
                return $account;
            }

            $account->save();

            // Which fields changed, never their values.
            Log::info('billing.account_updated', ['account_id' => $account->id]);
            $this->recordAudit->handle('billing.account_updated', $account, ['fields' => $fields]);

            return $account;
        });
    }
}
