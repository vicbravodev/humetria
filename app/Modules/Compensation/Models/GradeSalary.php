<?php

declare(strict_types=1);

namespace App\Modules\Compensation\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Compensation\GradeSalaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The daily salary of a job grade (a category and level of Organization, kept here by its id) from a date on. The salary
 * with the latest effective_from not after a date is the one in force on that date.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $job_grade_id
 * @property int $daily_salary_cents
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'job_grade_id', 'daily_salary_cents', 'effective_from'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(GradeSalaryFactory::class)]
final class GradeSalary extends Model
{
    /** @use HasFactory<GradeSalaryFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'daily_salary_cents' => 'integer',
            'effective_from' => 'immutable_date',
        ];
    }
}
