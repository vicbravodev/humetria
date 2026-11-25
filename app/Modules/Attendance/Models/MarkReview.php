<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Models;

use App\Concerns\BelongsToCurrentTenant;
use App\Modules\Attendance\Enums\ReviewDecision;
use Carbon\CarbonImmutable;
use Database\Factories\Attendance\MarkReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasVersion4Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * What Recursos Humanos decided about a mark made without a connection (criterio 5.4): verified, or incorrect with its
 * note. Insert-only: a new decision is a new row, and the one in force is the last by `seq`.
 *
 * @property string $id
 * @property int $seq
 * @property string $tenant_id
 * @property string $mark_id
 * @property ReviewDecision $decision
 * @property int $reviewer_id
 * @property string|null $note
 * @property CarbonImmutable $created_at
 */
#[Fillable(['tenant_id', 'mark_id', 'decision', 'reviewer_id', 'note'])]
#[Table('attendance_mark_reviews')]
#[UseFactory(MarkReviewFactory::class)]
final class MarkReview extends Model
{
    /** @use HasFactory<MarkReviewFactory> */
    use BelongsToCurrentTenant, HasFactory, HasVersion4Uuids;

    public const null UPDATED_AT = null;

    /**
     * The decision in force of each mark (the one with the greatest `seq`), keyed by mark; a mark with no review is absent.
     *
     * @param  list<string>  $markIds
     * @return array<string, self>
     */
    public static function latestByMark(array $markIds): array
    {
        if ($markIds === []) {
            return [];
        }

        $latest = [];

        foreach (self::query()->whereIn('id', self::query()->selectRaw('(array_agg(id order by seq desc))[1]')->whereIn('mark_id', $markIds)->groupBy('mark_id'))->get() as $review) {
            $latest[$review->mark_id] = $review;
        }

        return $latest;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['decision' => ReviewDecision::class, 'reviewer_id' => 'integer', 'seq' => 'integer'];
    }
}
