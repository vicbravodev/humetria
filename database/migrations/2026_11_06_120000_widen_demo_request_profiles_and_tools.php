<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The landing now offers the complaints box and Compensación, and unions can ask for a demo too.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->constrain(
            ['company', 'consultant', 'union'],
            ['nom_035', 'nom_037', 'ley_silla', 'complaints', 'compensation'],
        );
    }

    /**
     * Fails while a union or one of the new tools is stored: requests are never deleted to roll back.
     */
    public function down(): void
    {
        $this->constrain(['company', 'consultant'], ['nom_035', 'nom_037', 'ley_silla']);
    }

    /**
     * @param  list<string>  $profiles
     * @param  list<string>  $tools
     */
    private function constrain(array $profiles, array $tools): void
    {
        $profileList = implode(', ', array_map(fn (string $profile): string => "'{$profile}'", $profiles));
        $toolList = json_encode($tools, JSON_THROW_ON_ERROR);

        DB::statement(<<<SQL
            ALTER TABLE demo_requests
                DROP CONSTRAINT demo_requests_profile_valid,
                DROP CONSTRAINT demo_requests_tools_valid,
                ADD CONSTRAINT demo_requests_profile_valid CHECK (profile IN ({$profileList})),
                ADD CONSTRAINT demo_requests_tools_valid CHECK (
                    CASE WHEN jsonb_typeof(tools) = 'array'
                        THEN jsonb_array_length(tools) > 0 AND tools <@ '{$toolList}'::jsonb
                        ELSE false
                    END
                )
        SQL);
    }
};
