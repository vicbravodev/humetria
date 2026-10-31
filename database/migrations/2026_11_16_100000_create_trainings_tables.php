<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The trainings given at a work center (numerals 5.9 and 8.1, criterio C-24): the day, how it was given, which of the
 * 8.1 topics it covered and who attended. The due date of each person is derived from these, never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->date('held_on');
            $table->string('modality', 10);
            $table->jsonb('topics');
            $table->string('evidence_path', 200)->nullable();
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['tenant_id', 'work_center_id', 'held_on']);
            $table->unique(['id', 'tenant_id'], 'trainings_id_tenant_unique');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE trainings
                ADD CONSTRAINT trainings_modality_known CHECK (modality IN ('remote', 'in_person', 'mixed')),
                ADD CONSTRAINT trainings_topics_not_empty CHECK (jsonb_typeof(topics) = 'array' AND jsonb_array_length(topics) > 0)
        SQL);

        Schema::create('training_attendees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('training_id');
            $table->uuid('employee_id');
            $table->timestampsTz();

            $table->foreign(['training_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('trainings')->restrictOnDelete();
            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->unique(['training_id', 'employee_id']);
            $table->index(['tenant_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_attendees');
        Schema::dropIfExists('trainings');
    }
};
