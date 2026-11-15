<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a demo request came from: the campaign it is credited to (the last known channel, or the first when there is no
 * later one) and the first known channel. A utm_* is a line of up to 100 characters, a referrer is only a host (up to 255,
 * never a URL). "No data" is null and never an empty text, so a blank value is a check violation. No click ids of
 * Google Ads (gclid, gbraid, wbraid): the owner decided not to keep them.
 */
return new class extends Migration
{
    /** @var array<string, int> */
    private const array COLUMNS = [
        'utm_source' => 100,
        'utm_medium' => 100,
        'utm_campaign' => 100,
        'referrer_host' => 255,
        'first_utm_source' => 100,
        'first_utm_medium' => 100,
        'first_utm_campaign' => 100,
        'first_referrer_host' => 255,
    ];

    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column => $length) {
                $table->string($column, $length)->nullable();
            }
        });

        foreach (array_keys(self::COLUMNS) as $column) {
            DB::statement("ALTER TABLE demo_requests ADD CONSTRAINT demo_requests_{$column}_filled CHECK (btrim({$column}) <> '')");
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::COLUMNS) as $column) {
            DB::statement("ALTER TABLE demo_requests DROP CONSTRAINT demo_requests_{$column}_filled");
        }

        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::COLUMNS));
        });
    }
};
