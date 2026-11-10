<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Enums\StatementStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Billing\StatementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a company owes for a calendar month, or the correction of one that was issued. A draft can still be changed; an
 * issued one cannot (the database refuses it). It does not store whether it is paid: that is its total minus the
 * payments applied to it. Read across companies by the console, so it has a tenant_id but no per-company scope.
 *
 * @property string $id
 * @property string $billing_account_id
 * @property string $tenant_id
 * @property string|null $adjusts_statement_id
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property StatementStatus $status
 * @property string $currency
 * @property int $subtotal_cents
 * @property int $vat_cents
 * @property int $total_cents
 * @property array<string, mixed> $snapshot
 * @property string|null $reason
 * @property CarbonImmutable|null $issued_at
 * @property int|null $issued_by
 * @property int $created_by
 * @property CarbonImmutable $created_at
 */
#[Fillable(['billing_account_id', 'tenant_id', 'adjusts_statement_id', 'period_start', 'period_end', 'status', 'currency', 'subtotal_cents', 'vat_cents', 'total_cents', 'snapshot', 'reason', 'issued_at', 'issued_by', 'created_by'])]
#[UseFactory(StatementFactory::class)]
final class Statement extends Model
{
    /** @use HasFactory<StatementFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<BillingAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id');
    }

    /**
     * @return HasMany<StatementLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StatementLine::class)->orderBy('position');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'immutable_date',
            'period_end' => 'immutable_date',
            'status' => StatementStatus::class,
            'snapshot' => 'array',
            'issued_at' => 'immutable_datetime',
        ];
    }
}
