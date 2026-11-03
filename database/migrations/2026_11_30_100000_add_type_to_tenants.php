<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An account is a company (every one that exists) or a union. It is chosen when the account is created and never changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('type', 20)->default('company');
        });

        DB::statement("ALTER TABLE tenants ADD CONSTRAINT tenants_type_known CHECK (type IN ('company', 'union'))");
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
