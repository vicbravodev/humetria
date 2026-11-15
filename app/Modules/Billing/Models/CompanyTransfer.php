<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Billing\CompanyTransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A company moved from one payer to another (or to none) from a cut, with the reason. Kept for good: the history of who
 * paid a company. Read across companies by the console, so it has a tenant_id but no per-company scope.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $from_account_id
 * @property string|null $to_account_id
 * @property CarbonImmutable $effective_on
 * @property string $reason
 * @property string|null $profile_before
 * @property string|null $profile_after
 * @property int $created_by
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'from_account_id', 'to_account_id', 'effective_on', 'reason', 'profile_before', 'profile_after', 'created_by'])]
#[UseFactory(CompanyTransferFactory::class)]
final class CompanyTransfer extends Model
{
    /** @use HasFactory<CompanyTransferFactory> */
    use HasFactory, HasVersion4Uuids;

    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_on' => 'immutable_date'];
    }
}
