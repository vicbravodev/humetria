<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A reminder issues a new portal link; the one the person may have open keeps working for a while, so a list half
 * answered is not lost. Only the latest replaced link is kept, and only its hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_links', function (Blueprint $table) {
            $table->char('previous_token_hash', 64)->nullable()->unique();
            $table->timestampTz('previous_valid_until')->nullable();
        });

        DB::statement('ALTER TABLE portal_links ADD CONSTRAINT portal_links_previous_pair CHECK ((previous_token_hash IS NULL) = (previous_valid_until IS NULL))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE portal_links DROP CONSTRAINT portal_links_previous_pair');

        Schema::table('portal_links', function (Blueprint $table) {
            $table->dropUnique(['previous_token_hash']);
            $table->dropColumn(['previous_token_hash', 'previous_valid_until']);
        });
    }
};
