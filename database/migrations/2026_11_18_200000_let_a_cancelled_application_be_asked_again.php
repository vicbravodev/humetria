<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A cancelled checklist application no longer blocks asking for the same section again: only one that is neither answered
 * nor cancelled does (a person who ended and came back is asked afresh).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX checklist_applications_one_pending');
        DB::statement('CREATE UNIQUE INDEX checklist_applications_one_pending ON checklist_applications (workplace_id, section) WHERE submitted_at IS NULL AND cancelled_at IS NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX checklist_applications_one_pending');
        DB::statement('CREATE UNIQUE INDEX checklist_applications_one_pending ON checklist_applications (workplace_id, section) WHERE submitted_at IS NULL');
    }
};
