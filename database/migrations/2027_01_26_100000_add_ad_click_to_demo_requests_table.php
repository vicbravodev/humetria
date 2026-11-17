<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The last ad click of whoever asked for a demo (gclid, gbraid or wbraid), so the conversion can be imported into Google
 * Ads. Type and id go together or not at all, and `demos:forget-ad-clicks` empties both 90 days after the request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->string('ad_click_type', 10)->nullable();
            $table->string('ad_click_id', 255)->nullable();
        });

        DB::statement("ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_ad_click_type_known CHECK (ad_click_type IN ('gclid', 'gbraid', 'wbraid'))");
        DB::statement("ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_ad_click_id_filled CHECK (btrim(ad_click_id) <> '')");
        DB::statement('ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_ad_click_together CHECK ((ad_click_type IS NULL) = (ad_click_id IS NULL))');
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropColumn(['ad_click_type', 'ad_click_id']);
        });
    }
};
