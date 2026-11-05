<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Compensation\Enums\RaiseUnit;
use Carbon\CarbonImmutable;
use Database\Factories\Compensation\ScenarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A proposal to revise the contract. `group_changes` holds the raise of each staff group in basis points (500 is 5 %) or, when
 * `raise_unit` is an amount, in cents a month; the trusted staff have null when they copy the unionized raise. `term_changes` are the benefits whose terms the proposal
 * changes, each with its new value (hundredths, see BenefitKind) and, when it grows with seniority, its new table.
 * `assumptions` are the projection's, by key, each with its value, source and the day it was set.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $name
 * @property CarbonImmutable $effective_on
 * @property int $retroactive_months
 * @property int $horizon_years 3 to 5
 * @property RaiseUnit $raise_unit
 * @property array{unionized?: ?int, trusted?: ?int} $group_changes
 * @property list<array{benefit_id: string, value: int, tenure_table: ?list<array{from_years: int, value: int}>}> $term_changes
 * @property array<string, array{value_bp: int, source: string, set_on: string}> $assumptions
 * @property ?string $default_state
 * @property ?CarbonImmutable $archived_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'name', 'effective_on', 'retroactive_months', 'horizon_years', 'raise_unit', 'group_changes', 'term_changes', 'assumptions', 'default_state', 'archived_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(ScenarioFactory::class)]
final class Scenario extends Model
{
    /** @use HasFactory<ScenarioFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /** A scenario raises by a percent unless it says otherwise, as the column does. */
    protected $attributes = ['raise_unit' => 'percent'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'effective_on' => 'immutable_date',
            'retroactive_months' => 'integer',
            'horizon_years' => 'integer',
            'raise_unit' => RaiseUnit::class,
            'group_changes' => 'array',
            'term_changes' => 'array',
            'assumptions' => 'array',
            'archived_at' => 'immutable_datetime',
        ];
    }
}
