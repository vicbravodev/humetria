<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('is_demo')->default(false);
        });

        // A unique index over a constant, only on the demo rows: at most one demo company can exist.
        DB::statement('CREATE UNIQUE INDEX tenants_one_demo ON tenants ((true)) WHERE is_demo');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX tenants_one_demo');

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('is_demo');
        });
    }
};
