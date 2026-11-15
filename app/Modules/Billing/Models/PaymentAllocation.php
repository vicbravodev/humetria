<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Billing\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The part of a payment that settles a statement. The database refuses one that would pay more than the payment holds or
 * more than the statement is worth.
 *
 * @property string $id
 * @property string $payment_id
 * @property string $statement_id
 * @property int $amount_cents
 * @property CarbonImmutable $created_at
 */
#[Fillable(['payment_id', 'statement_id', 'amount_cents'])]
#[UseFactory(PaymentAllocationFactory::class)]
final class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use HasFactory, HasVersion4Uuids;

    public const null UPDATED_AT = null;

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<Statement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class);
    }
}
