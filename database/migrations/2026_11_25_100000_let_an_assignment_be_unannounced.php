<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * NOM-035, complaints channel: an assignment the handler was never told about has no notified position (null); one told up to
 * entry n has n. The hourly digest tells each handler how many cases are new to them and how many have a reply.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE complaint_assignments ALTER COLUMN notified_position DROP NOT NULL, ALTER COLUMN notified_position DROP DEFAULT');
        DB::statement('UPDATE complaint_assignments SET notified_position = NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE complaint_assignments SET notified_position = 0 WHERE notified_position IS NULL');
        DB::statement('ALTER TABLE complaint_assignments ALTER COLUMN notified_position SET DEFAULT 0, ALTER COLUMN notified_position SET NOT NULL');
    }
};
