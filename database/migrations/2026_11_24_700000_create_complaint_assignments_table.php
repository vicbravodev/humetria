<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * NOM-035, complaints channel: who may open a complaint. Seeing a case takes a current assignment; being a handler is not
 * enough, and a handler designated later does not see what came before. Only days are kept (assigned_on, ended_on): the
 * system's assignment at receipt must not carry the hour the complaint came. An assignment ends, it is never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('complaint_id');
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->date('assigned_on');
            $table->date('ended_on')->nullable();
            $table->string('end_reason', 16)->nullable();
            $table->smallInteger('notified_position')->default(0);

            $table->foreign(['complaint_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('complaints')->restrictOnDelete();
            $table->index(['tenant_id', 'user_id']);
            $table->index('complaint_id');
        });

        DB::statement('CREATE UNIQUE INDEX complaint_assignments_open_unique ON complaint_assignments (complaint_id, user_id) WHERE ended_on IS NULL');
        DB::statement(<<<'SQL'
            ALTER TABLE complaint_assignments
                ADD CONSTRAINT complaint_assignments_end_reason_known CHECK (end_reason IS NULL OR end_reason IN ('recused', 'handler_ended', 'reassigned', 'member_removed')),
                ADD CONSTRAINT complaint_assignments_ended_has_reason CHECK ((ended_on IS NULL) = (end_reason IS NULL))
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_assignments');
    }
};
