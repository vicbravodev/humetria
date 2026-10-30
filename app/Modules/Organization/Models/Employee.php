<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\SearchesText;
use App\Modules\Organization\Enums\Gender;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person who works for the company, in one of its work centers. Never deleted: deactivated, so the evaluations
 * that name them keep their history (NOM-035 num. 5.8 and 10.4).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property string $area_id
 * @property string $name
 * @property string|null $email
 * @property string|null $mobile
 * @property Gender $gender
 * @property string $job_title
 * @property string|null $employee_number
 * @property CarbonImmutable|null $deactivated_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'area_id', 'name', 'email', 'mobile', 'gender', 'job_title', 'employee_number', 'deactivated_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(EmployeeFactory::class)]
final class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, SearchesText;

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
