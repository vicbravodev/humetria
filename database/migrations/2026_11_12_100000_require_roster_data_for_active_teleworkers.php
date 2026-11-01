<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A person is in the roster (active) only with the data of numeral 5.1, kept nullable while they are aspirants. The
 * equipment (datum k) is a list that arrives later; until then, the reason for having none. And a checklist asked of a
 * period that ended is cancelled, never deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teleworkers', function (Blueprint $table) {
            $table->text('no_equipment_reason')->nullable();
        });
        Schema::table('checklist_applications', function (Blueprint $table) {
            $table->timestampTz('cancelled_at')->nullable();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE teleworkers
                ADD CONSTRAINT teleworkers_active_has_roster_data CHECK (
                    status <> 'active' OR (
                        marital_status IS NOT NULL AND activities IS NOT NULL AND job_profile IS NOT NULL AND contact_phone IS NOT NULL
                        AND street IS NOT NULL AND exterior_number IS NOT NULL AND neighborhood IS NOT NULL
                        AND postal_code IS NOT NULL AND municipality IS NOT NULL AND state IS NOT NULL
                    )
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teleworkers DROP CONSTRAINT teleworkers_active_has_roster_data');
        Schema::table('checklist_applications', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
        Schema::table('teleworkers', function (Blueprint $table) {
            $table->dropColumn('no_equipment_reason');
        });
    }
};
