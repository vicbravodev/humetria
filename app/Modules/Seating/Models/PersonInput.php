<?php

declare(strict_types=1);

namespace App\Modules\Seating\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Database\Factories\Seating\PersonInputFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What a person answered about their footwear and discomfort (1 to 3 points each, as the DOF's tables), with the privacy
 * notice they accepted. Identified health data: it never reaches a log, the audit trail, the viewer or a public link.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $employee_id
 * @property string $work_center_id
 * @property int $footwear
 * @property int $discomfort
 * @property string|null $note
 * @property CarbonImmutable $notice_accepted_at
 * @property CarbonImmutable $submitted_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'employee_id', 'work_center_id', 'footwear', 'discomfort', 'note', 'notice_accepted_at', 'submitted_at'])]
#[UseFactory(PersonInputFactory::class)]
final class PersonInput extends Model
{
    /** @use HasFactory<PersonInputFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['footwear' => 'integer', 'discomfort' => 'integer', 'notice_accepted_at' => 'immutable_datetime', 'submitted_at' => 'immutable_datetime'];
    }
}
