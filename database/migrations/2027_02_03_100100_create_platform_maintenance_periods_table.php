<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Each time the superadmin put the whole platform in maintenance (every company's panel read only), why, and until when. One open
 * at most: the one without an end.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_maintenance_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reason', 500);
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('create unique index platform_maintenance_periods_one_open on platform_maintenance_periods ((ended_at is null)) where ended_at is null');
        DB::statement('alter table platform_maintenance_periods add constraint platform_maintenance_periods_dates_check check (ended_at is null or ended_at >= started_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_maintenance_periods');
    }
};
