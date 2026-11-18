<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fewest anonymous answers a group needs before its result is shown (criterio C-23) is the company's to choose, from 1
 * («sin mínimo») to 50. Null follows the platform's default (`results.min_group`, 5): a company that never touches it
 * keeps the protection it had.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->unsignedSmallInteger('min_group')->nullable();
        });

        DB::statement('ALTER TABLE tenants ADD CONSTRAINT tenants_min_group_range CHECK (min_group IS NULL OR min_group BETWEEN 1 AND 50)');
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('min_group');
        });
    }
};
