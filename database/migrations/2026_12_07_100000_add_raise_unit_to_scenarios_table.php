<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether the raise of a scenario is a percent (group_changes in basis points) or a fixed amount a month (group_changes in
 * cents), decision A13 of the redesign. Every scenario saved before is a percent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->string('raise_unit', 10)->default('percent');
        });

        DB::statement("ALTER TABLE scenarios ADD CONSTRAINT scenarios_raise_unit_known CHECK (raise_unit IN ('percent', 'amount'))");
    }

    public function down(): void
    {
        Schema::table('scenarios', function (Blueprint $table) {
            $table->dropColumn('raise_unit');
        });
    }
};
