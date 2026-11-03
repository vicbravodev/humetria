<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\RiskAcknowledgmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * That a person was informed of the risks and measures of one signed assessment, with the moment they confirmed it in their portal. One per assessment.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $assessment_id
 * @property CarbonImmutable $acknowledged_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'assessment_id', 'acknowledged_at'])]
#[UseFactory(RiskAcknowledgmentFactory::class)]
final class RiskAcknowledgment extends Model
{
    /** @use HasFactory<RiskAcknowledgmentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['acknowledged_at' => 'immutable_datetime'];
    }
}
