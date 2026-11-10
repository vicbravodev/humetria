<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The reporting line: each person's direct manager, a person of their own company, and the seniority the company
 * recognizes when it is not the hire date. The database keeps the line free of cycles on its own: nobody is their own
 * manager, and nobody reports, directly or not, to someone below them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->uuid('manager_id')->nullable();
            $table->date('seniority_on')->nullable();

            $table->foreign(['manager_id', 'tenant_id'])->references(['id', 'tenant_id'])->on('employees')->restrictOnDelete();
            $table->index(['tenant_id', 'manager_id']);
        });

        DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_manager_not_self CHECK (manager_id <> id)');

        // Two changes in the same company wait for each other: without the lock neither would see the other's and
        // together they could close a cycle. The walk goes up one manager at a time and stops at 10,000 steps, so a
        // cycle already in the data (a restore with the trigger disabled) cannot hang a write.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION employees_manager_acyclic() RETURNS trigger LANGUAGE plpgsql AS $$
            DECLARE
                ancestor uuid := NEW.manager_id;
                steps integer := 0;
            BEGIN
                PERFORM pg_advisory_xact_lock(hashtextextended(NEW.tenant_id::text, 0));

                WHILE ancestor IS NOT NULL AND steps < 10000 LOOP
                    IF ancestor = NEW.id THEN
                        RAISE EXCEPTION 'employees_manager_cycle' USING ERRCODE = '23514';
                    END IF;

                    SELECT manager_id INTO ancestor FROM employees WHERE id = ancestor;
                    steps := steps + 1;
                END LOOP;

                RETURN NEW;
            END;
            $$;

            CREATE TRIGGER employees_manager_acyclic BEFORE INSERT OR UPDATE OF manager_id ON employees
                FOR EACH ROW WHEN (NEW.manager_id IS NOT NULL AND NEW.manager_id <> NEW.id)
                EXECUTE FUNCTION employees_manager_acyclic();
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER employees_manager_acyclic ON employees; DROP FUNCTION employees_manager_acyclic();');
        DB::statement('ALTER TABLE employees DROP CONSTRAINT employees_manager_not_self');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['manager_id', 'tenant_id']);
            $table->dropIndex(['tenant_id', 'manager_id']);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['manager_id', 'seniority_on']);
        });
    }
};
