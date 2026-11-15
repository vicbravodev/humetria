<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The monthly renewal drafts statements with no person behind them: a statement with no author is the system's. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE statements ALTER COLUMN created_by DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE statements ALTER COLUMN created_by SET NOT NULL');
    }
};
