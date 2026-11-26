<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The version of a company's logo for dark mode (a logo with dark letters disappears on a dark page): a PNG twice the size it
 * is shown at, and that size. Only a company with its logo for light may have it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_dark_path')->nullable();
            $table->unsignedSmallInteger('logo_dark_width')->nullable();
            $table->unsignedSmallInteger('logo_dark_height')->nullable();
        });

        DB::statement('ALTER TABLE tenants ADD CONSTRAINT tenants_logo_dark_whole CHECK ((logo_dark_path IS NULL AND logo_dark_width IS NULL AND logo_dark_height IS NULL) OR (logo_dark_path IS NOT NULL AND logo_dark_width > 0 AND logo_dark_height > 0 AND logo_path IS NOT NULL))');
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['logo_dark_path', 'logo_dark_width', 'logo_dark_height']);
        });
    }
};
