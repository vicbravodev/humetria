<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Concerns\SearchesText;
use App\Modules\Billing\Enums\AccountKind;
use Carbon\CarbonImmutable;
use Database\Factories\Billing\BillingAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Who pays: a company for itself or its group (direct), or a consultant for the companies of their clients. Platform data
 * of Humetría: it belongs to no company, so it has no tenant_id and the console reads it across all of them.
 *
 * @property string $id
 * @property AccountKind $kind
 * @property string $legal_name
 * @property string $rfc
 * @property string|null $tax_regime
 * @property string|null $postal_code
 * @property string|null $cfdi_use
 * @property string $contact_name
 * @property string $contact_email
 * @property string|null $contact_phone
 * @property string|null $notes
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['kind', 'legal_name', 'rfc', 'tax_regime', 'postal_code', 'cfdi_use', 'contact_name', 'contact_email', 'contact_phone', 'notes'])]
#[UseFactory(BillingAccountFactory::class)]
final class BillingAccount extends Model
{
    /** @use HasFactory<BillingAccountFactory> */
    use HasFactory, HasVersion4Uuids, SearchesText;

    /**
     * Every company it has paid, current or past.
     *
     * @return HasMany<AccountCompany, $this>
     */
    public function companies(): HasMany
    {
        return $this->hasMany(AccountCompany::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => AccountKind::class];
    }
}
