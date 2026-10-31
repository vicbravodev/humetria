<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The framework's own tables were created with timestamps without a time zone. Their values were always written in
 * UTC, so each is read as UTC and becomes timestamptz: the same instant, now unambiguous.
 */
return new class extends Migration
{
    /** @var array<string, list<string>> */
    private const array COLUMNS = [
        'users' => ['email_verified_at', 'two_factor_confirmed_at', 'created_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'passkeys' => ['last_used_at', 'created_at', 'updated_at'],
        'failed_jobs' => ['failed_at'],
    ];

    public function up(): void
    {
        $this->alter('timestamptz');
    }

    public function down(): void
    {
        $this->alter('timestamp');
    }

    private function alter(string $type): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            $changes = array_map(fn (string $column): string => "ALTER COLUMN {$column} TYPE {$type}(0) USING {$column} AT TIME ZONE 'UTC'", $columns);
            DB::statement("ALTER TABLE {$table} ".implode(', ', $changes));
        }
    }
};
