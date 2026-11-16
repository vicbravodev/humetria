<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NOM-035, complaints channel: a complaint that came after its channel's daily cap is still received but held, with no handler
 * and no mail, until the next day's release. A flag and not a timestamp: like the rest of the table it keeps only the day
 * (received_on), since the hour would tell who sent it. Only a new, unpurged complaint can be held.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE complaints
                ADD COLUMN held boolean NOT NULL DEFAULT false,
                ADD CONSTRAINT complaints_held_only_when_new CHECK (NOT held OR (status = 'new' AND purged_on IS NULL))
        SQL);
        DB::statement('CREATE INDEX complaints_held_index ON complaints (received_on) WHERE held');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX complaints_held_index');
        DB::statement('ALTER TABLE complaints DROP CONSTRAINT complaints_held_only_when_new, DROP COLUMN held');
    }
};
