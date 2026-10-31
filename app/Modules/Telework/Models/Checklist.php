<?php

declare(strict_types=1);

namespace App\Modules\Telework\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Telework\Enums\ChecklistStatus;
use Carbon\CarbonImmutable;
use Database\Factories\Telework\ChecklistFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One version of a work center's verification checklist. Version 1 starts as a draft copied from the base list.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $work_center_id
 * @property int $version
 * @property ChecklistStatus $status
 * @property CarbonImmutable|null $published_at
 * @property int|null $published_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['tenant_id', 'work_center_id', 'version', 'status', 'published_at', 'published_by'])]
#[UseFactory(ChecklistFactory::class)]
final class Checklist extends Model
{
    /** @use HasFactory<ChecklistFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    /**
     * @return HasMany<ChecklistQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class)->orderBy('position');
    }

    /** The newest published version of a work center's checklist; later applications and drafts start from it. */
    public static function latestPublished(string $workCenterId): ?self
    {
        return self::query()->where('work_center_id', $workCenterId)->where('status', ChecklistStatus::Published)->orderByDesc('version')->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => ChecklistStatus::class, 'published_at' => 'immutable_datetime'];
    }
}
