<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The WhatsApp meter, and the unit cost of Meta with the price charged on each usage line (so the platform's margin is the
 * difference). The line stays append-only: adding columns is not changing a row. Same pattern as the mail and storage meters.
 */
return new class extends Migration
{
    private const array TABLES = [
        'usage_counters' => 'usage_counters_meter_known',
        'usage_reservations' => 'usage_reservations_meter_known',
        'usage_events' => 'usage_events_meter_known',
        'usage_grants' => 'usage_grants_meter_known',
    ];

    public function up(): void
    {
        $this->allow("'assistant_tokens', 'email_campaign', 'storage_bytes', 'whatsapp'");

        DB::statement('ALTER TABLE usage_events ADD COLUMN unit_cost_micros bigint NULL, ADD COLUMN unit_price_micros bigint NULL, ADD COLUMN cost_currency char(3) NULL');
        DB::statement('ALTER TABLE usage_events ADD CONSTRAINT usage_events_cost_complete CHECK ((unit_cost_micros IS NULL AND unit_price_micros IS NULL AND cost_currency IS NULL) OR (unit_cost_micros IS NOT NULL AND unit_price_micros IS NOT NULL AND cost_currency IS NOT NULL AND unit_cost_micros >= 0 AND unit_price_micros >= unit_cost_micros))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE usage_events DROP CONSTRAINT usage_events_cost_complete');
        DB::statement('ALTER TABLE usage_events DROP COLUMN unit_cost_micros, DROP COLUMN unit_price_micros, DROP COLUMN cost_currency');

        // Fails (and loses nothing) if WhatsApp was already counted: the events are append-only.
        $this->allow("'assistant_tokens', 'email_campaign', 'storage_bytes'");
    }

    private function allow(string $meters): void
    {
        foreach (self::TABLES as $table => $constraint) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (meter IN ({$meters}))");
        }
    }
};
