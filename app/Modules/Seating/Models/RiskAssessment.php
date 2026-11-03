<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Instruments\Data\RiskLevel;
use App\Modules\Seating\Enums\AssessmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\RiskAssessmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The risk assessment of one person who works standing: the seven conditions (1 to 3 points each), the total and the level
 * the platform calculates, and the facts of Quinta C. Identified health data: it never reaches a log, the audit trail or the
 * viewer. A signed one is final (the database refuses to change it); the person is assessed again with a new draft, and the
 * signed ones are the history.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $position_id
 * @property string $work_center_id
 * @property array<string, int> $scores
 * @property int|null $total
 * @property RiskLevel|null $level
 * @property string|null $instrument_version
 * @property AssessmentStatus $status
 * @property CarbonImmutable|null $verified_on
 * @property string|null $other_aspects
 * @property array<string, mixed>|null $snapshot
 * @property bool $person_provided_health_data
 * @property string|null $recorded_in_document_id
 * @property bool $reassessment_due
 * @property int $created_by
 * @property int|null $signed_by
 * @property CarbonImmutable|null $signed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'position_id', 'work_center_id', 'scores', 'total', 'level', 'instrument_version', 'status', 'verified_on', 'other_aspects', 'snapshot', 'person_provided_health_data', 'recorded_in_document_id', 'reassessment_due', 'created_by', 'signed_by', 'signed_at'])]
#[UseFactory(RiskAssessmentFactory::class)]
final class RiskAssessment extends Model
{
    /** @use HasFactory<RiskAssessmentFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'snapshot' => 'array',
            'level' => RiskLevel::class,
            'status' => AssessmentStatus::class,
            'verified_on' => 'immutable_date',
            'signed_at' => 'immutable_datetime',
            'person_provided_health_data' => 'boolean',
            'reassessment_due' => 'boolean',
        ];
    }
}
