<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One answer of a checklist application: yes, no or not_applicable. Null only on a sensitive question left unanswered
 * (consent declined). `risk_note` is personal data: never logged.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string $question_id
 * @property string|null $answer
 * @property string|null $risk_note
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'application_id', 'question_id', 'answer', 'risk_note'])]
final class ChecklistAnswer extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;
}
