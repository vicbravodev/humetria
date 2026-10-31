<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('stores every date and time with its time zone (timestamptz), framework tables included', function (): void {
    $naive = DB::table('information_schema.columns')
        ->where('table_schema', 'public')
        ->where('data_type', 'timestamp without time zone')
        ->orderBy('table_name')->orderBy('column_name')
        ->get(['table_name', 'column_name'])
        ->map(fn (object $column): string => "{$column->table_name}.{$column->column_name}")
        ->all();

    expect($naive)->toBe([]);
});

it('reads a user\'s date and time back as the same UTC instant', function (): void {
    $user = User::factory()->create(['email_verified_at' => '2026-10-30 20:02:00']);

    expect(DB::scalar("select to_char(email_verified_at at time zone 'UTC', 'YYYY-MM-DD HH24:MI') from users where id = ?", [$user->id]))
        ->toBe('2026-10-30 20:02');
});
