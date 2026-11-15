<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Data\AccountData;
use App\Modules\Billing\Enums\AccountKind;

/** The DTO of a payer account out of a validated form. */
trait BuildsAccountData
{
    public function toData(AccountKind $kind): AccountData
    {
        $optional = fn (string $field): ?string => $this->filled($field) ? $this->string($field)->toString() : null;

        return new AccountData(
            kind: $kind,
            legalName: $this->string('legal_name')->toString(),
            rfc: $this->string('rfc')->toString(),
            contactName: $this->string('contact_name')->toString(),
            contactEmail: $this->string('contact_email')->toString(),
            taxRegime: $optional('tax_regime'),
            postalCode: $optional('postal_code'),
            cfdiUse: $optional('cfdi_use'),
            contactPhone: $optional('contact_phone'),
            notes: $optional('notes'),
        );
    }
}
