<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rediseño v2, board V3-Aviso (decisión A17): the follow-up of an accident notice (6.9). What was done goes in
 * `review_actions`, a list of known actions, only on a notice of a risk of work; a review carries what was done or a note
 * (the reviews before this one have only their note). It closes once, after the review, with its evidence on the private
 * disk. RH is reminded once (`reminded_at`), and the Comisión may be asked to review the place again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_notices', function (Blueprint $table) {
            $table->jsonb('review_actions')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->string('evidence_path')->nullable();
            $table->timestampTz('reminded_at')->nullable();
            $table->timestampTz('place_review_requested_at')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE worker_notices
                DROP CONSTRAINT worker_notices_review_complete,
                ADD CONSTRAINT worker_notices_review_complete CHECK (
                    (reviewed_by IS NULL) = (reviewed_at IS NULL)
                    AND (reviewed_at IS NOT NULL OR (review_note IS NULL AND review_actions IS NULL))
                    AND (reviewed_at IS NULL OR review_note IS NOT NULL OR review_actions IS NOT NULL)
                ),
                ADD CONSTRAINT worker_notices_actions_of_accidents CHECK (
                    review_actions IS NULL OR (kind = 'work_risk' AND jsonb_typeof(review_actions) = 'array' AND jsonb_array_length(review_actions) > 0
                        AND review_actions <@ '["medical_care", "place_adjusted", "tasks_changed", "leave", "other"]'::jsonb)
                ),
                ADD CONSTRAINT worker_notices_close_complete CHECK (
                    (closed_at IS NULL) = (closed_by IS NULL) AND (closed_at IS NULL) = (evidence_path IS NULL)
                    AND (closed_at IS NULL OR (kind = 'work_risk' AND reviewed_at IS NOT NULL))
                ),
                ADD CONSTRAINT worker_notices_follow_up_of_accidents CHECK ((reminded_at IS NULL AND place_review_requested_at IS NULL) OR kind = 'work_risk')
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE worker_notices
                DROP CONSTRAINT worker_notices_follow_up_of_accidents,
                DROP CONSTRAINT worker_notices_close_complete,
                DROP CONSTRAINT worker_notices_actions_of_accidents,
                DROP CONSTRAINT worker_notices_review_complete,
                ADD CONSTRAINT worker_notices_review_complete CHECK ((reviewed_by IS NULL) = (reviewed_at IS NULL) AND (reviewed_at IS NULL) = (review_note IS NULL))
        SQL);

        Schema::table('worker_notices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('closed_by');
            $table->dropColumn(['review_actions', 'closed_at', 'evidence_path', 'reminded_at', 'place_review_requested_at']);
        });
    }
};
