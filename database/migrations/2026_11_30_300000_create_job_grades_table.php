<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The job grades of a company's pay scale: a category and a level ("Operador" "A"). They carry no salary: the money
 * lives in Compensation. The key (id, tenant_id) lets an employee point to a grade of their own company only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('category', 120);
            $table->string('level', 60);
            $table->timestampsTz();
            $table->unique(['id', 'tenant_id'], 'job_grades_id_tenant_unique');
        });
        DB::statement("ALTER TABLE job_grades ADD CONSTRAINT job_grades_filled CHECK (btrim(category) <> '' AND btrim(level) <> '')");
        DB::statement('CREATE UNIQUE INDEX job_grades_tenant_category_level_unique ON job_grades (tenant_id, lower(category), lower(level))');
    }

    public function down(): void
    {
        Schema::dropIfExists('job_grades');
    }
};
