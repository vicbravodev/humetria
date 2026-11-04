<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-035, complaints channel: the thread of a case, its internal notes and its own history. The messages of the person
 * carry a day (occurred_on) and never an hour; what a handler does carries the moment (occurred_at), since a handler acts as
 * themselves and is audited. The order is a position, per case. The text is encrypted by the model; meta holds states and
 * user ids, never words.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('complaint_id');
            $table->smallInteger('position');
            $table->string('kind', 20);
            $table->foreignId('author_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('body')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->date('occurred_on')->nullable();

            $table->foreign(['complaint_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('complaints')->restrictOnDelete();
            $table->unique(['complaint_id', 'position']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE complaint_entries
                ADD CONSTRAINT complaint_entries_kind_known CHECK (kind IN ('reporter_message', 'handler_message', 'internal_note', 'status_change', 'assignment_change', 'recusal')),
                ADD CONSTRAINT complaint_entries_reporter_has_a_day_only CHECK ((kind = 'reporter_message') = (occurred_on IS NOT NULL AND occurred_at IS NULL)),
                ADD CONSTRAINT complaint_entries_handler_has_a_moment CHECK ((kind = 'reporter_message') OR (occurred_at IS NOT NULL AND occurred_on IS NULL)),
                ADD CONSTRAINT complaint_entries_reporter_has_no_author CHECK (kind <> 'reporter_message' OR author_id IS NULL)
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_entries');
    }
};
