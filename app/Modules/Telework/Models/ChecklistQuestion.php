<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Instruments\Data\Favorable;
use App\Modules\Instruments\Data\Requirement;
use App\Modules\Telework\Enums\QuestionOrigin;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A question of a checklist version. `favorable` is the answer that flags no risk; `base_code` is the base-list question
 * it was copied from, if any.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $checklist_id
 * @property int $section
 * @property int $position
 * @property string $code
 * @property string $heading
 * @property string $text
 * @property QuestionOrigin $origin
 * @property Favorable $favorable
 * @property Requirement|null $requirement
 * @property bool $sensitive
 * @property string|null $base_code
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'checklist_id', 'section', 'position', 'code', 'heading', 'text', 'origin', 'favorable', 'requirement', 'sensitive', 'base_code'])]
final class ChecklistQuestion extends Model
{
    use BelongsToCurrentTenant, HasVersion4Uuids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => QuestionOrigin::class,
            'favorable' => Favorable::class,
            'requirement' => Requirement::class,
            'sensitive' => 'boolean',
        ];
    }
}
