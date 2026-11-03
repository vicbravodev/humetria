<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\SearchesText;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\JobGradeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A category and level of the company's pay scale ("Operador" "A"). It carries no salary.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $category
 * @property string $level
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'category', 'level'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(JobGradeFactory::class)]
final class JobGrade extends Model
{
    /** @use HasFactory<JobGradeFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, SearchesText;
}
