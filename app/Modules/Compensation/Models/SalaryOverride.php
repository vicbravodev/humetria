<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\SearchesText;
use Carbon\CarbonImmutable;
use Database\Factories\Compensation\SalaryOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a person really earns per day from a date on, when it is not the salary of their category and level. The person is
 * the company's employee number (the ID de la empresa). A person has one row per date; the one in force on a day is the
 * latest that started on or before it, and the earlier ones stay as history.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_number
 * @property int $daily_salary_cents
 * @property CarbonImmutable $effective_from
 * @property ?string $note
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_number', 'daily_salary_cents', 'effective_from', 'note'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(SalaryOverrideFactory::class)]
final class SalaryOverride extends Model
{
    /** @use HasFactory<SalaryOverrideFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, SearchesText;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'daily_salary_cents' => 'integer',
            'effective_from' => 'immutable_date',
        ];
    }
}
