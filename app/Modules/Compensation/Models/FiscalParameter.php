<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Models;

use Carbon\CarbonImmutable;
use Database\Factories\Compensation\FiscalParameterFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A fiscal or legal value of the platform (UMA, a contribution rate, a state's payroll tax) with its vigencia, source and
 * consulted date. No tenant_id: it is the same for every account. The value is value_int / 10^scale.
 *
 * @property string $id
 * @property string $key
 * @property ?string $state
 * @property CarbonImmutable $valid_from
 * @property ?CarbonImmutable $valid_to
 * @property int $value_int
 * @property int<0, max> $scale
 * @property string $source
 * @property CarbonImmutable $consulted_on
 * @property ?string $note
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['key', 'state', 'valid_from', 'valid_to', 'value_int', 'scale', 'source', 'consulted_on', 'note'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(FiscalParameterFactory::class)]
final class FiscalParameter extends Model
{
    /** @use HasFactory<FiscalParameterFactory> */
    use HasFactory, HasVersion4Uuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'value_int' => 'integer',
            'scale' => 'integer',
            'consulted_on' => 'immutable_date',
        ];
    }
}
