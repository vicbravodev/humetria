<?php

declare(strict_types=1);

namespace App\Modules\Leave\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Leave\Enums\PolicyScope;
use Carbon\CarbonImmutable;
use Database\Factories\Leave\LeavePolicyAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The policy of a target (criterio C-15): the default of the company, a staff group ("unionized" or "trusted") or a job
 * grade. One per target.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $leave_policy_id
 * @property PolicyScope $scope
 * @property string|null $staff_group
 * @property string|null $job_grade_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'leave_policy_id', 'scope', 'staff_group', 'job_grade_id'])]
#[UseFactory(LeavePolicyAssignmentFactory::class)]
final class LeavePolicyAssignment extends Model
{
    /** @use HasFactory<LeavePolicyAssignmentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return BelongsTo<LeavePolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scope' => PolicyScope::class];
    }
}
