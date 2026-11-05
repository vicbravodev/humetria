<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Compensation\ContractTermFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The terms of a benefit from a date on: its value (a whole number of hundredths, see BenefitKind) and, when it grows with
 * seniority, its table of values by years of service. The term with the latest effective_from not after a date is the one
 * in force on that date.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $benefit_id
 * @property CarbonImmutable $effective_from
 * @property int $value
 * @property ?list<array{from_years: int, value: int}> $tenure_table ascending by from_years, none repeated
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'benefit_id', 'effective_from', 'value', 'tenure_table'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(ContractTermFactory::class)]
final class ContractTerm extends Model
{
    /** @use HasFactory<ContractTermFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /** @return BelongsTo<Benefit, $this> */
    public function benefit(): BelongsTo
    {
        return $this->belongsTo(Benefit::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_from' => 'immutable_date',
            'value' => 'integer',
            'tenure_table' => 'array',
        ];
    }
}
