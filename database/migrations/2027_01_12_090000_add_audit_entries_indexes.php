<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The audit viewer reads newest first and narrows by day and family of event (company already has its index). Creating an index
 * is not blocked by the append-only trigger, which only stops UPDATE, DELETE and TRUNCATE. A prefix search on event needs its
 * pattern ops. Single-column and ascending on purpose: entries of one moment keep the order they were written in for anyone who
 * reads them oldest first, as a two-column or descending index would turn around.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX audit_entries_created_at_index ON audit_entries (created_at)');
        DB::statement('CREATE INDEX audit_entries_event_prefix_index ON audit_entries (event varchar_pattern_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS audit_entries_created_at_index');
        DB::statement('DROP INDEX IF EXISTS audit_entries_event_prefix_index');
    }
};
