<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\Gender;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A company's areas and employees. An employee works in a work center and an area of their own company: composite
 * foreign keys tie both to the same tenant_id. They are deactivated, never deleted. Email and employee number are
 * unique inside the company; an employee is reachable by email, mobile or both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_centers', function (Blueprint $table) {
            $table->unique(['id', 'tenant_id'], 'work_centers_id_tenant_unique');
        });

        Schema::create('areas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->timestampsTz();
            $table->unique(['id', 'tenant_id'], 'areas_id_tenant_unique');
        });
        DB::statement("ALTER TABLE areas ADD CONSTRAINT areas_name_filled CHECK (btrim(name) <> '')");
        DB::statement('CREATE UNIQUE INDEX areas_tenant_name_unique ON areas (tenant_id, lower(name))');

        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->uuid('work_center_id');
            $table->uuid('area_id');
            $table->string('name', 200);
            $table->string('email', 255)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('gender', 10);
            $table->string('job_title', 150);
            $table->string('employee_number', 50)->nullable();
            $table->timestampTz('deactivated_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['work_center_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('work_centers')->restrictOnDelete();
            $table->foreign(['area_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('areas')->restrictOnDelete();
            $table->index(['tenant_id', 'work_center_id']);
            $table->index(['tenant_id', 'area_id']);
        });

        $genders = implode(', ', array_map(fn (Gender $gender): string => "'{$gender->value}'", Gender::cases()));

        DB::statement(<<<SQL
            ALTER TABLE employees
                ADD CONSTRAINT employees_name_filled CHECK (btrim(name) <> ''),
                ADD CONSTRAINT employees_job_title_filled CHECK (btrim(job_title) <> ''),
                ADD CONSTRAINT employees_employee_number_filled CHECK (employee_number IS NULL OR btrim(employee_number) <> ''),
                ADD CONSTRAINT employees_email_lowercase CHECK (email = lower(email)),
                ADD CONSTRAINT employees_mobile_format CHECK (mobile ~ '^[0-9]{10}$'),
                ADD CONSTRAINT employees_contact_present CHECK (email IS NOT NULL OR mobile IS NOT NULL),
                ADD CONSTRAINT employees_gender_known CHECK (gender IN ({$genders}))
        SQL);
        DB::statement('CREATE UNIQUE INDEX employees_tenant_email_unique ON employees (tenant_id, email) WHERE email IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX employees_tenant_number_unique ON employees (tenant_id, lower(employee_number)) WHERE employee_number IS NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('areas');

        Schema::table('work_centers', function (Blueprint $table) {
            $table->dropUnique('work_centers_id_tenant_unique');
        });
    }
};
