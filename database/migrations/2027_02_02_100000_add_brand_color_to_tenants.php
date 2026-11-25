<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The accent a company picked for its own pages (white label): a six-digit hex color, or null for the accent of Humetría.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('brand_color', 7)->nullable();
        });

        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_brand_color_hex CHECK (brand_color IS NULL OR brand_color ~ '^#[0-9a-f]{6}$')");
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('brand_color');
        });
    }
};
