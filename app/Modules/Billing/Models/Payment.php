<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Billing\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money an account paid by transfer (SPEI), recorded by hand. It is never edited or deleted: a wrong one is reversed, with a
 * reason, and its applications stop counting. Platform data of Humetría, with no tenant_id.
 *
 * @property string $id
 * @property string $billing_account_id
 * @property int $amount_cents
 * @property CarbonImmutable $paid_on
 * @property string $reference
 * @property string|null $note
 * @property int $recorded_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $reversed_at
 * @property int|null $reversed_by
 * @property string|null $reversal_reason
 */
#[Fillable(['billing_account_id', 'amount_cents', 'paid_on', 'reference', 'note', 'recorded_by', 'reversed_at', 'reversed_by', 'reversal_reason'])]
#[UseFactory(PaymentFactory::class)]
final class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasVersion4Uuids;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<BillingAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(BillingAccount::class, 'billing_account_id');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * The payments that still count: not reversed.
     *
     * @param  Builder<self>  $query
     */
    public function scopeStanding(Builder $query): void
    {
        $query->whereNull('reversed_at');
    }

    public function isReversed(): bool
    {
        return $this->reversed_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['paid_on' => 'immutable_date', 'reversed_at' => 'immutable_datetime'];
    }
}
