<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a person decided about a company's access, with the reason and the moment: read only, suspended or archived.
 * It replaces deactivated_at, which could say neither why nor how far. Every company deactivated until now becomes
 * suspended, its old date kept as the moment and its reason «migrada».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('restriction', 20)->nullable();
            $table->text('restriction_reason')->nullable();
            $table->timestampTz('restricted_at')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE tenants
            SET restriction = 'suspended', restriction_reason = 'migrada', restricted_at = deactivated_at
            WHERE deactivated_at IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE tenants
                ADD CONSTRAINT tenants_restriction_known CHECK (restriction IS NULL OR restriction IN ('read_only', 'suspended', 'archived')),
                ADD CONSTRAINT tenants_restriction_complete CHECK (
                    (restriction IS NULL AND restriction_reason IS NULL AND restricted_at IS NULL)
                    OR (restriction IS NOT NULL AND restriction_reason IS NOT NULL AND btrim(restriction_reason) <> '' AND restricted_at IS NOT NULL)
                )
        SQL);

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn('deactivated_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->timestampTz('deactivated_at')->nullable();
        });

        DB::statement("UPDATE tenants SET deactivated_at = restricted_at WHERE restriction IN ('suspended', 'archived')");

        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn(['restriction', 'restriction_reason', 'restricted_at']);
        });
    }
};
