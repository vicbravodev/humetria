<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * A union receives from the company a file that may carry no contact, gender, work center, area, name or job title,
 * so the database stops requiring them. What stays: a person is told apart by a name or by the company's employee
 * number. Company accounts keep requiring everything in the form and the importer.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const array COLUMNS = ['name', 'gender', 'work_center_id', 'area_id', 'job_title'];

    public function up(): void
    {
        DB::statement('ALTER TABLE employees DROP CONSTRAINT employees_contact_present');

        foreach (self::COLUMNS as $column) {
            DB::statement("ALTER TABLE employees ALTER COLUMN {$column} DROP NOT NULL");
        }

        DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_identified CHECK (name IS NOT NULL OR employee_number IS NOT NULL)');
    }

    /**
     * Fails, before changing anything, while some person would break the old constraints: delete or complete those
     * people first.
     */
    public function down(): void
    {
        $incomplete = DB::table('employees')
            ->where(fn (Builder $people): Builder => $people->whereNull('name')->orWhereNull('gender')->orWhereNull('work_center_id')
                ->orWhereNull('area_id')->orWhereNull('job_title')->orWhere(fn (Builder $contact): Builder => $contact->whereNull('email')->whereNull('mobile')))
            ->count();

        if ($incomplete > 0) {
            throw new RuntimeException("Cannot restore the employee constraints: {$incomplete} people lack a name, gender, work center, area, job title or contact. Complete or remove them first.");
        }

        DB::statement('ALTER TABLE employees DROP CONSTRAINT employees_identified');

        foreach (self::COLUMNS as $column) {
            DB::statement("ALTER TABLE employees ALTER COLUMN {$column} SET NOT NULL");
        }

        DB::statement('ALTER TABLE employees ADD CONSTRAINT employees_contact_present CHECK (email IS NOT NULL OR mobile IS NOT NULL)');
    }
};
