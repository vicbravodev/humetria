<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** A telework period never ends before it started. */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE teleworkers ADD CONSTRAINT teleworkers_ended_after_started CHECK (ended_at IS NULL OR started_at IS NULL OR ended_at >= started_at)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE teleworkers DROP CONSTRAINT teleworkers_ended_after_started');
    }
};
