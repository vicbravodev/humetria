<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Database\Factories\Billing\StatementLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a statement: what it is, how much (a discount or a credit is negative) and, in words, how that was reached.
 *
 * @property string $id
 * @property string $statement_id
 * @property int $position
 * @property string $code
 * @property string $label
 * @property int $amount_cents
 * @property string $explanation
 * @property string|null $charge_id
 */
#[Fillable(['statement_id', 'position', 'code', 'label', 'amount_cents', 'explanation', 'charge_id'])]
#[UseFactory(StatementLineFactory::class)]
final class StatementLine extends Model
{
    /** @use HasFactory<StatementLineFactory> */
    use HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<Statement, $this>
     */
    public function statement(): BelongsTo
    {
        return $this->belongsTo(Statement::class);
    }
}
