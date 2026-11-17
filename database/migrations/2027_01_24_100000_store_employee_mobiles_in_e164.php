<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A mobile is kept in E.164 (`+525512345678`), so a number of another country fits. The ones there were Mexico's
 * 10 digits: they get their +52.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE employees DROP CONSTRAINT employees_mobile_format');
        DB::statement('ALTER TABLE employees ALTER COLUMN mobile TYPE varchar(16)');
        DB::statement("UPDATE employees SET mobile = '+52' || mobile WHERE mobile IS NOT NULL");
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_mobile_e164 CHECK (mobile ~ '^\\+[1-9][0-9]{6,14}$')");
    }

    /** Fails, before changing anything, while some mobile is not Mexican: it would not fit in 10 digits. */
    public function down(): void
    {
        $abroad = DB::table('employees')->whereNotNull('mobile')->where('mobile', 'not like', '+52%')->count();

        if ($abroad > 0) {
            throw new RuntimeException("Cannot go back to 10-digit mobiles: {$abroad} people have one of another country. Change or remove them first.");
        }

        DB::statement('ALTER TABLE employees DROP CONSTRAINT employees_mobile_e164');
        DB::statement('UPDATE employees SET mobile = substr(mobile, 4) WHERE mobile IS NOT NULL');
        DB::statement('ALTER TABLE employees ALTER COLUMN mobile TYPE varchar(20)');
        DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_mobile_format CHECK (mobile ~ '^[0-9]{10}$')");
    }
};
