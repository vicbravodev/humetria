<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-035, complaints channel: a complaint or report. Like the anonymous answers of Guías II and III (criterio C-22) it has
 * no created_at and no updated_at, since the hour it came would tell who sent it: only the day (received_on). Its id is a
 * random UUID, never ordered by time. It points to no employee, invitation, portal link, campaign or answer. The texts are
 * encrypted by the model; the key to follow it up exists only as a hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('channel_id');
            $table->string('reference', 8);
            $table->string('access_key_hash', 64);
            $table->string('type', 24);
            $table->string('category', 32)->nullable();
            $table->string('respondent_kind', 20);
            $table->uuid('work_center_id')->nullable();
            $table->string('identity_mode', 12);
            $table->string('status', 10);
            $table->string('resolution', 24)->nullable();
            $table->boolean('on_hold')->default(false);
            $table->text('description')->nullable();
            $table->text('respondent_text')->nullable();
            $table->date('occurred_on')->nullable();
            $table->date('received_on');
            $table->timestampTz('acknowledged_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->date('closed_on')->nullable();
            $table->date('purged_on')->nullable();

            $table->foreign(['channel_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('complaint_channels')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->unique(['id', 'tenant_id'], 'complaints_id_tenant_unique');
            $table->unique(['tenant_id', 'reference'], 'complaints_reference_unique');
            $table->unique('access_key_hash');
            $table->index(['tenant_id', 'status', 'received_on']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE complaints
                ADD CONSTRAINT complaints_type_known CHECK (type IN ('unfavorable_practice', 'workplace_violence', 'traumatic_event_notice')),
                ADD CONSTRAINT complaints_category_fits_type CHECK (
                    category IS NULL OR (type = 'workplace_violence' AND category IN ('harassment', 'bullying', 'mistreatment'))
                    OR (type = 'unfavorable_practice' AND category IN ('workload_or_hours', 'unclear_responsibilities', 'lack_of_training', 'lack_of_recognition', 'communication_or_participation', 'discrimination', 'other'))
                ),
                ADD CONSTRAINT complaints_respondent_kind_known CHECK (respondent_kind IN ('direct_supervisor', 'coworker', 'hr', 'management', 'third_party', 'unknown')),
                ADD CONSTRAINT complaints_identity_mode_known CHECK (identity_mode IN ('anonymous', 'confidential')),
                ADD CONSTRAINT complaints_status_known CHECK (status IN ('new', 'in_review', 'closed')),
                ADD CONSTRAINT complaints_resolution_known CHECK (resolution IS NULL OR resolution IN ('measures_taken', 'no_action_needed', 'referred_externally', 'insufficient_information', 'withdrawn', 'discarded_spam')),
                ADD CONSTRAINT complaints_closed_has_resolution CHECK ((status = 'closed') = (resolution IS NOT NULL)),
                ADD CONSTRAINT complaints_closed_has_day CHECK ((status = 'closed') = (closed_on IS NOT NULL AND closed_at IS NOT NULL)),
                ADD CONSTRAINT complaints_text_gone_only_when_purged CHECK ((purged_on IS NULL) = (description IS NOT NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
