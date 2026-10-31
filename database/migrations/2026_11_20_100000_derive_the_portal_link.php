<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The portal link is derived from the company, the employee and a version (see `PortalLink::tokenFor`): a reminder sends
 * the same link again instead of replacing it, so the replaced link kept for a while is gone. RH revokes a link by moving
 * its version.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE portal_links DROP CONSTRAINT portal_links_previous_pair');

        Schema::table('portal_links', function (Blueprint $table) {
            $table->dropUnique(['previous_token_hash']);
            $table->dropColumn(['previous_token_hash', 'previous_valid_until']);
            $table->unsignedInteger('version')->default(1);
        });

        DB::statement('ALTER TABLE portal_links ADD CONSTRAINT portal_links_version_positive CHECK (version >= 1)');
    }

    public function down(): void
    {
        Schema::table('portal_links', function (Blueprint $table) {
            $table->dropColumn('version');
            $table->char('previous_token_hash', 64)->nullable()->unique();
            $table->timestampTz('previous_valid_until')->nullable();
        });

        DB::statement('ALTER TABLE portal_links ADD CONSTRAINT portal_links_previous_pair CHECK ((previous_token_hash IS NULL) = (previous_valid_until IS NULL))');
    }
};
