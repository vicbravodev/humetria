<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Quejas: a handler whose role becomes the one a case is against stops holding it (criterio C-45), so an assignment may now
 * end with the reason `role_conflict`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE complaint_assignments DROP CONSTRAINT complaint_assignments_end_reason_known');
        DB::statement("ALTER TABLE complaint_assignments ADD CONSTRAINT complaint_assignments_end_reason_known CHECK (end_reason IS NULL OR end_reason IN ('recused', 'handler_ended', 'reassigned', 'member_removed', 'role_conflict'))");
    }

    public function down(): void
    {
        DB::statement("UPDATE complaint_assignments SET end_reason = 'reassigned' WHERE end_reason = 'role_conflict'");
        DB::statement('ALTER TABLE complaint_assignments DROP CONSTRAINT complaint_assignments_end_reason_known');
        DB::statement("ALTER TABLE complaint_assignments ADD CONSTRAINT complaint_assignments_end_reason_known CHECK (end_reason IS NULL OR end_reason IN ('recused', 'handler_ended', 'reassigned', 'member_removed'))");
    }
};
