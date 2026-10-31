<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reordering renumbers in two phases: first every position goes negative, so the unique (checklist, position) never meets
 * a duplicate halfway. The check therefore forbids only zero instead of requiring positions of 1 or more.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE checklist_questions DROP CONSTRAINT checklist_questions_position_positive');
        DB::statement('ALTER TABLE checklist_questions ADD CONSTRAINT checklist_questions_position_not_zero CHECK (position <> 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE checklist_questions DROP CONSTRAINT checklist_questions_position_not_zero');
        DB::statement('ALTER TABLE checklist_questions ADD CONSTRAINT checklist_questions_position_positive CHECK (position >= 1)');
    }
};
