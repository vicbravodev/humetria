<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The minimum of people per group a campaign applies, frozen from the company's when it launches: the invitation
 * promised that number to whoever answers. Null in a draft, which follows the company's until it launches.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_group')->nullable();
        });

        DB::statement('ALTER TABLE campaigns ADD CONSTRAINT campaigns_min_group_range CHECK (min_group IS NULL OR min_group BETWEEN 1 AND 50)');
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->dropColumn('min_group');
        });
    }
};
