<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
        $this->allow("'assistant_tokens', 'email_campaign', 'storage_bytes'");
    }

    public function down(): void
    {
        // Fails (and loses nothing) if mail or storage were already counted: the events are append-only.
        $this->allow("'assistant_tokens'");
    }

    private function allow(string $meters): void
    {
        foreach (self::TABLES as $table => $constraint) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$constraint} CHECK (meter IN ({$meters}))");
        }
    }
};
