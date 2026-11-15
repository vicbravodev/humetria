<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a company has any row in tenant_modules. Most do not (no plan, nothing taken by hand) and then every page can know
 * what it has, which is everything that exists, without asking for the rows: the flag rides on the company row every request
 * already reads. It only ever goes from false to true (a row is written), so a stale false cannot hide a limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->boolean('has_module_rows')->default(false);
        });

        DB::statement('UPDATE tenants SET has_module_rows = true WHERE EXISTS (SELECT 1 FROM tenant_modules WHERE tenant_modules.tenant_id = tenants.id)');
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('has_module_rows');
        });
    }
};
