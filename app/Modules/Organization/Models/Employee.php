<?php

declare(strict_types=1);

namespace App\Modules\Organization\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Concerns\MatchesManyIds;
use App\Concerns\SearchesText;
use App\Modules\Organization\Enums\Gender;
use App\Modules\Organization\Enums\StaffGroup;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person who works for the company, in one of its work centers. Never deleted: deactivated, so the evaluations
 * that name them keep their history (NOM-035 num. 5.8 and 10.4).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $work_center_id
 * @property string|null $area_id
 * @property string|null $name
 * @property string|null $email
 * @property string|null $mobile
 * @property Gender|null $gender
 * @property string|null $job_title
 * @property string|null $employee_number
 * @property string|null $job_grade_id
 * @property CarbonImmutable|null $hired_on
 * @property StaffGroup|null $staff_group
 * @property string|null $state
 * @property string|null $manager_id
 * @property CarbonImmutable|null $seniority_on
 * @property CarbonImmutable|null $deactivated_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'area_id', 'name', 'email', 'mobile', 'gender', 'job_title', 'employee_number', 'job_grade_id', 'hired_on', 'staff_group', 'state', 'manager_id', 'seniority_on', 'deactivated_at'])] // @pest-mutate-ignore: RemoveArrayItem
#[UseFactory(EmployeeFactory::class)]
final class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids, MatchesManyIds, SearchesText;

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * The direct manager: a person of the same company, active or not.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /**
     * Whoever reports directly to this person.
     *
     * @return HasMany<Employee, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    /** How the person reads where one is picked: «Nombre · Puesto», with the company ID for a name a union left out. */
    public function label(): string
    {
        return implode(' · ', array_filter([$this->name ?? $this->employee_number ?? '', $this->job_title ?? ''], fn (string $part): bool => $part !== ''));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'staff_group' => StaffGroup::class,
            'hired_on' => 'immutable_date',
            'seniority_on' => 'immutable_date',
            'deactivated_at' => 'immutable_datetime',
        ];
    }
}
