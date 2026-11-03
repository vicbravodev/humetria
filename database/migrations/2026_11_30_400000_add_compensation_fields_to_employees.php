<?php

declare(strict_types=1);

use App\Modules\Organization\Enums\StaffGroup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the contract calculator needs to know about a person, all of it optional and none of it money: the grade of the
 * pay scale (a job grade of their own company), the hire date, the staff group and the state where they work when
 * they have no work center to say it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->uuid('job_grade_id')->nullable();
            $table->date('hired_on')->nullable();
            $table->string('staff_group', 20)->nullable();
            $table->string('state', 3)->nullable();

            $table->foreign(['job_grade_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('job_grades')->restrictOnDelete();
            $table->index(['tenant_id', 'job_grade_id']);
        });

        $groups = implode(', ', array_map(fn (StaffGroup $group): string => "'{$group->value}'", StaffGroup::cases()));
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_staff_group_known CHECK (staff_group IN ({$groups}))");
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['job_grade_id', 'tenant_id']);
            $table->dropIndex(['tenant_id', 'job_grade_id']);
        });
        DB::statement('ALTER TABLE employees DROP CONSTRAINT employees_staff_group_known');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['job_grade_id', 'hired_on', 'staff_group', 'state']);
        });
    }
};
