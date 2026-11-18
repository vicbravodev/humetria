<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The landing form asks for four contact fields; the profile, the size and the tools are an optional
 * "tell us your case". A request without them stores the profile and the size as null and no tools as `[]`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE demo_requests
                ALTER COLUMN profile DROP NOT NULL,
                ALTER COLUMN workforce DROP NOT NULL,
                ALTER COLUMN tools SET DEFAULT '[]'::jsonb
        SQL);

        $this->constrainTools('jsonb_array_length(tools) >= 0');
    }

    /**
     * Fails while a request without profile, size or tools is stored: requests are never deleted to roll back.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE demo_requests
                ALTER COLUMN profile SET NOT NULL,
                ALTER COLUMN workforce SET NOT NULL,
                ALTER COLUMN tools DROP DEFAULT
        SQL);

        $this->constrainTools('jsonb_array_length(tools) > 0');
    }

    private function constrainTools(string $length): void
    {
        $tools = json_encode([
            'nom_035', 'nom_037', 'ley_silla', 'complaints', 'compensation',
            'attendance', 'leave', 'org_chart', 'engagement',
        ], JSON_THROW_ON_ERROR);

        DB::statement(<<<SQL
            ALTER TABLE demo_requests
                DROP CONSTRAINT demo_requests_tools_valid,
                ADD CONSTRAINT demo_requests_tools_valid CHECK (
                    CASE WHEN jsonb_typeof(tools) = 'array'
                        THEN {$length} AND tools <@ '{$tools}'::jsonb
                        ELSE false
                    END
                )
        SQL);
    }
};
