<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Models\AttendanceMark;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Whether a person's marks still follow one another (MarkType::allowedAfter) once a mark is moved, or one is added: an
 * end before its start, a start while another day is open or a break with no day are refused. Only the stretch the change
 * touches is read: from the last start before it (the original left out) to the first start after it, included, so that
 * a start added or moved must leave the day before it closed. What came before that start is taken as it is.
 */
final class CorrectedSequence
{
    public static function follows(string $employeeId, ?AttendanceMark $original, MarkType $type, CarbonImmutable $at): bool
    {
        $was = $original->marked_at ?? $at;
        $from = $at->min($was)->utc();
        $to = $at->max($was)->utc();
        $others = fn (): Builder => EffectiveMarks::of($employeeId)->when($original, fn (Builder $query, AttendanceMark $mark) => $query->whereKeyNot($mark->id));
        $before = $others()->where('type', MarkType::Start)->where('marked_at', '<=', $from)->latest('marked_at')->first(['marked_at'])?->marked_at;
        $after = $others()->where('type', MarkType::Start)->where('marked_at', '>', $to)->oldest('marked_at')->first(['marked_at'])?->marked_at;

        $marks = $others()
            ->when($before, fn (Builder $query, CarbonImmutable $start) => $query->where('marked_at', '>=', $start->utc()))
            ->when($after, fn (Builder $query, CarbonImmutable $start) => $query->where('marked_at', '<=', $start->utc()))
            ->orderBy('marked_at')
            ->get(['type', 'marked_at']);

        $sequence = [];
        $placed = false;
        foreach ($marks as $mark) {
            if (! $placed && $mark->marked_at?->greaterThan($at) === true) {
                $sequence[] = $type;
                $placed = true;
            }

            $sequence[] = $mark->type;
        }

        if (! $placed) {
            $sequence[] = $type;
        }

        $last = null;
        foreach ($sequence as $next) {
            if (! in_array($next, MarkType::allowedAfter($last), true)) {
                return false;
            }

            $last = $next;
        }

        return true;
    }
}
