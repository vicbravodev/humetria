<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Models;

use App\Modules\Announcements\Enums\AnnouncementAudience;
use App\Modules\Announcements\Enums\AnnouncementKind;
use App\Modules\Announcements\Enums\AnnouncementState;
use Carbon\CarbonImmutable;
use Database\Factories\Announcements\AnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What Humetría tells the companies in their panel, between two moments. Platform data: no company owns it.
 *
 * @property string $id
 * @property AnnouncementKind $kind
 * @property AnnouncementAudience $audience
 * @property string|null $plan
 * @property string $title
 * @property string $message
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
#[Fillable(['kind', 'audience', 'plan', 'title', 'message', 'starts_at', 'ends_at', 'published_at'])]
#[UseFactory(AnnouncementFactory::class)]
final class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, HasVersion4Uuids;

    protected $table = 'platform_announcements';

    public function state(): AnnouncementState
    {
        return match (true) {
            $this->published_at === null => AnnouncementState::Draft,
            now()->greaterThanOrEqualTo($this->ends_at) => AnnouncementState::Ended,
            now()->lessThan($this->starts_at) => AnnouncementState::Scheduled,
            default => AnnouncementState::Active,
        };
    }

    /**
     * The ones still to come or running, drafts included.
     *
     * @param  Builder<self>  $query
     */
    public function scopeCurrent(Builder $query): void
    {
        $query->where('ends_at', '>', now());
    }

    /**
     * The ones whose end already passed.
     *
     * @param  Builder<self>  $query
     */
    public function scopeHistory(Builder $query): void
    {
        $query->where('ends_at', '<=', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AnnouncementKind::class,
            'audience' => AnnouncementAudience::class,
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
