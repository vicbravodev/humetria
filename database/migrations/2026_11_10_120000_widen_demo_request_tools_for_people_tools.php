<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The landing now offers attendance, leave, the org chart and climate and pulse.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->constrain([
            'nom_035', 'nom_037', 'ley_silla', 'complaints', 'compensation',
            'attendance', 'leave', 'org_chart', 'engagement',
        ]);
    }

    /**
     * Fails while a request for one of the new tools is stored: requests are never deleted to roll back.
     */
    public function down(): void
    {
        $this->constrain(['nom_035', 'nom_037', 'ley_silla', 'complaints', 'compensation']);
    }

    /**
     * @param  list<string>  $tools
     */
    private function constrain(array $tools): void
    {
        $toolList = json_encode($tools, JSON_THROW_ON_ERROR);

        DB::statement(<<<SQL
            ALTER TABLE demo_requests
                DROP CONSTRAINT demo_requests_tools_valid,
                ADD CONSTRAINT demo_requests_tools_valid CHECK (
                    CASE WHEN jsonb_typeof(tools) = 'array'
                        THEN jsonb_array_length(tools) > 0 AND tools <@ '{$toolList}'::jsonb
                        ELSE false
                    END
                )
        SQL);
    }
};
