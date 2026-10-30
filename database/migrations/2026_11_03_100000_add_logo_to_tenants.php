<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The company's logo for the emails its people get: a PNG twice the size it is shown at, and that size.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('logo_width')->nullable();
            $table->unsignedSmallInteger('logo_height')->nullable();
        });

        DB::statement('ALTER TABLE tenants ADD CONSTRAINT tenants_logo_whole CHECK ((logo_path IS NULL AND logo_width IS NULL AND logo_height IS NULL) OR (logo_path IS NOT NULL AND logo_width > 0 AND logo_height > 0))');
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'logo_width', 'logo_height']);
        });
    }
};
