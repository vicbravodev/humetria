<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A photo of a workplace, on the private disk. `question_id` is null for a photo of the whole checklist.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $application_id
 * @property string|null $question_id
 * @property string $path
 * @property CarbonImmutable $uploaded_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'application_id', 'question_id', 'path', 'uploaded_at'])]
final class ChecklistPhoto extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['uploaded_at' => 'immutable_datetime'];
    }
}
