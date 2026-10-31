<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\ReviewDecision;
use App\Modules\Telework\Enums\ReviewMethod;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\WorkplaceReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A decision of the Comisión about a workplace. Never edited or deleted: a later review replaces it as the current one.
 * `notes` and `action_plan` are personal data: never logged.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $workplace_id
 * @property string|null $application_id
 * @property ReviewMethod $method
 * @property ReviewDecision $decision
 * @property string $notes
 * @property string|null $action_plan
 * @property CarbonImmutable|null $plan_due_on
 * @property int $decided_by
 * @property CarbonImmutable $decided_at
 * @property CarbonImmutable|null $visited_on
 * @property string|null $visit_authorization_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'workplace_id', 'application_id', 'method', 'decision', 'notes', 'action_plan', 'plan_due_on', 'decided_by', 'decided_at', 'visited_on', 'visit_authorization_id'])]
#[UseFactory(WorkplaceReviewFactory::class)]
final class WorkplaceReview extends Model
{
    /** @use HasFactory<WorkplaceReviewFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => ReviewMethod::class,
            'decision' => ReviewDecision::class,
            'plan_due_on' => 'immutable_date',
            'decided_at' => 'immutable_datetime',
            'visited_on' => 'immutable_date',
        ];
    }
}
