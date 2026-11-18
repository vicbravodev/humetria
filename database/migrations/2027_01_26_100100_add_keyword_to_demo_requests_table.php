<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the ad of a demo request was shown for: the keyword, the ad and the match type of the final URL suffix of Google
 * Ads. A line of up to 100 characters (20 for the match type); "no data" is null, never a blank text.
 */
return new class extends Migration
{
    /** @var array<string, int> */
    private const array COLUMNS = ['utm_term' => 100, 'utm_content' => 100, 'match_type' => 20];

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
        Schema::table('demo_requests', function (Blueprint $table): void {
            $table->dropColumn(array_keys(self::COLUMNS));
        });
    }
};
