<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ley Silla: what a person answers in their portal about their footwear (condicionante d) and their discomfort (f). It is
 * identified health data (criterio C-18): only RH and the Comisión of the center read it, and only to assess. Every answer
 * stays; the latest one counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('person_inputs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('employee_id');
            $table->uuid('work_center_id');
            $table->unsignedSmallInteger('footwear');
            $table->unsignedSmallInteger('discomfort');
            $table->text('note')->nullable();
            $table->timestampTz('notice_accepted_at');
            $table->timestampTz('submitted_at');
            $table->timestampsTz();

            $table->foreign(['employee_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->index(['employee_id', 'submitted_at']);
            $table->unique(['id', 'tenant_id'], 'person_inputs_id_tenant_unique');
        });

        DB::statement('ALTER TABLE person_inputs ADD CONSTRAINT person_inputs_footwear_range CHECK (footwear BETWEEN 1 AND 3), ADD CONSTRAINT person_inputs_discomfort_range CHECK (discomfort BETWEEN 1 AND 3)');
    }

    public function down(): void
    {
        Schema::dropIfExists('person_inputs');
    }
};
