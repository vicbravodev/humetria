<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An aspirant who never joined the roster can be ended (the person left, or telework was dropped): only an active
 * period must have started.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE teleworkers DROP CONSTRAINT teleworkers_active_has_started');
        DB::statement("ALTER TABLE teleworkers ADD CONSTRAINT teleworkers_active_has_started CHECK (status <> 'active' OR started_at IS NOT NULL)");
    }

    public function down(): void
    {
        // NOT VALID: new writes are checked again, but aspirants ended without starting since `up()` are kept.
        DB::statement('ALTER TABLE teleworkers DROP CONSTRAINT teleworkers_active_has_started');
        DB::statement("ALTER TABLE teleworkers ADD CONSTRAINT teleworkers_active_has_started CHECK (status = 'aspirant' OR started_at IS NOT NULL) NOT VALID");
    }
};
