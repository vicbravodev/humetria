<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * One person who attended a training. Never deleted.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $training_id
 * @property string $employee_id
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'training_id', 'employee_id'])]
final class TrainingAttendee extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;
}
