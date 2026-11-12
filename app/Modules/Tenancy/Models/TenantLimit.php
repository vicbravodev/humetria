<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Models;

use Database\Factories\Tenancy\TenantLimitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What a company may have of one resource (seats, centers) and what it has. Read from inside a company (the reservation) and from
 * the console and the reconciliation across companies, so it has a tenant_id but no per-company scope.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $resource
 * @property int $amount
 * @property int $used
 */
#[Fillable(['tenant_id', 'resource', 'amount', 'used'])]
#[UseFactory(TenantLimitFactory::class)]
final class TenantLimit extends Model
{
    /** @use HasFactory<TenantLimitFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
