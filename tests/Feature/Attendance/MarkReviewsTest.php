<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Models\MarkReview;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Attendance\AttendanceMarkFactory;
use Database\Factories\Attendance\AttendanceOfflineMarkFactory;
use Database\Factories\Attendance\MarkReviewFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * What Recursos Humanos decides about a mark made without a connection is a new row, never an edit (criterio 5.4): the
 * decision in force is the last one. Each statement that must fail runs in its own savepoint: the test is one transaction.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
});

/** The error the database gave to a statement, or null when it went through. */
function reviewRefusal(Closure $statement): ?string
{
    try {
        DB::transaction($statement);
    } catch (QueryException $e) {
        return $e->getMessage();
    }

    return null;
}

/** Inserts a copy of `$base` with SQL, bypassing the casts, with a new id unless the overrides say otherwise. */
function insertReviewLike(MarkReview $base, array $overrides): void
{
    DB::table('attendance_mark_reviews')->insert([...Arr::except($base->getRawOriginal(), ['id', 'seq']), 'id' => Str::uuid()->toString(), ...$overrides]);
}

it('keeps a review of a mark with who made it, and the note of an incorrect one', function (): void {
    $verified = MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id])->refresh();
    $incorrect = MarkReviewFactory::new()->incorrect()->createOne(['tenant_id' => $this->tenant->id])->refresh();

    expect($verified->decision)->toBe(ReviewDecision::Verified)->and($verified->note)->toBeNull()
        ->and($verified->reviewer_id)->toBeInt()
        ->and($incorrect->decision)->toBe(ReviewDecision::Incorrect)->and($incorrect->note)->toBe('El quiosco del centro sí tenía luz.');
});

it('never edits nor deletes a review, not even with SQL', function (string $sql): void {
    $review = MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id]);

    expect(reviewRefusal(fn () => DB::statement(str_replace(':id', "'{$review->id}'", $sql))))->toContain('attendance_mark_reviews is insert-only');
})->with([
    'update' => ["update attendance_mark_reviews set note = 'otra' where id = :id"],
    'delete' => ['delete from attendance_mark_reviews where id = :id'],
    'truncate' => ['truncate attendance_mark_reviews cascade'],
]);

it('rejects a decision nobody knows, an incorrect one without a note, and a note that is too long', function (): void {
    $base = MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id]);

    expect(reviewRefusal(fn () => insertReviewLike($base, ['decision' => 'maybe'])))->toContain('attendance_mark_reviews_decision_known')
        ->and(reviewRefusal(fn () => insertReviewLike($base, ['decision' => 'incorrect', 'note' => null])))->toContain('attendance_mark_reviews_note_on_incorrect')
        ->and(reviewRefusal(fn () => insertReviewLike($base, ['decision' => 'incorrect', 'note' => '  Mal  '])))->toContain('attendance_mark_reviews_note_on_incorrect')
        ->and(reviewRefusal(fn () => insertReviewLike($base, ['decision' => 'incorrect', 'note' => 'Mal hecha'])))->toBeNull()
        ->and(reviewRefusal(fn () => insertReviewLike($base, ['note' => str_repeat('a', 501)])))->toContain('attendance_mark_reviews_note_length');
});

it('rejects a review of a mark of another company', function (): void {
    $base = MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id]);
    $markOfB = AttendanceMarkFactory::new()->createOne();

    expect(reviewRefusal(fn () => insertReviewLike($base, ['mark_id' => $markOfB->id])))->toContain('foreign key');
});

it('reads the last decision of each mark, even when two reviews share the instant', function (): void {
    $delivery = AttendanceOfflineMarkFactory::new()->accepted()->createOne(['tenant_id' => $this->tenant->id]);
    $instant = now()->startOfSecond();
    $first = MarkReviewFactory::new()->incorrect()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $delivery->mark_id, 'created_at' => $instant]);
    $second = MarkReviewFactory::new()->createOne(['tenant_id' => $this->tenant->id, 'mark_id' => $delivery->mark_id, 'created_at' => $instant]);
    $unreviewed = AttendanceMarkFactory::new()->createOne(['tenant_id' => $this->tenant->id]);

    $latest = MarkReview::latestByMark([(string) $delivery->mark_id, $unreviewed->id]);

    expect(array_keys($latest))->toBe([$delivery->mark_id])
        ->and($latest[(string) $delivery->mark_id]->id)->toBe($second->id)->not->toBe($first->id)
        ->and(MarkReview::latestByMark([]))->toBe([]);
});
