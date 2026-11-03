<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NOM-035, complaints channel: a person who withdraws their complaint closes it themselves, and what they do keeps no hour.
 * A case closed by a handler still carries the moment (closed_at); a withdrawn one carries only the day.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE complaints
                DROP CONSTRAINT complaints_closed_has_day,
                ADD CONSTRAINT complaints_closed_has_day CHECK ((status = 'closed') = (closed_on IS NOT NULL)),
                ADD CONSTRAINT complaints_closing_moment CHECK (
                    (status = 'closed' AND (closed_at IS NOT NULL OR resolution = 'withdrawn')) OR (status <> 'closed' AND closed_at IS NULL)
                )
        SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE complaints
                DROP CONSTRAINT complaints_closing_moment,
                DROP CONSTRAINT complaints_closed_has_day,
                ADD CONSTRAINT complaints_closed_has_day CHECK ((status = 'closed') = (closed_on IS NOT NULL AND closed_at IS NOT NULL))
        SQL);
    }
};
