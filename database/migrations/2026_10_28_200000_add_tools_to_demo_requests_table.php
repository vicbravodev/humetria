<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which HR tools a demo request is about. Requests from before this column existed were all about the NOM-035.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->jsonb('tools')->nullable();
        });

        DB::statement(<<<'SQL'
            UPDATE demo_requests SET tools = '["nom_035"]'::jsonb
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE demo_requests
                ALTER COLUMN tools SET NOT NULL,
                ADD CONSTRAINT demo_requests_tools_valid CHECK (
                    jsonb_typeof(tools) = 'array'
                    AND jsonb_array_length(tools) > 0
                    AND tools <@ '["nom_035","nom_037","ley_silla"]'::jsonb
                )
        SQL);
    }

    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->dropColumn('tools');
        });
    }
};
