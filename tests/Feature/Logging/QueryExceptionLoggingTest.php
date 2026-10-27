<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->logPath = storage_path('logs/query-exception-test.log');
    File::delete($this->logPath);
    config(['logging.channels.single.path' => $this->logPath]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');

    Route::middleware('web')->get('/__failing-query', fn () => DB::select(
        'select * from table_that_does_not_exist where email = ? and phone = ?',
        ['ana.secreta@empresa.mx', '+525512345678'],
    ));
});

afterEach(function (): void {
    File::delete($this->logPath);
});

it('never writes the bindings of a failed query to the log', function (): void {
    $this->get('/__failing-query')->assertServerError();

    $log = File::get($this->logPath);

    expect($log)->not->toContain('ana.secreta@empresa.mx')
        ->not->toContain('5512345678');
});

it('still reports the failed query, with its SQL and error code but no values', function (): void {
    $this->get('/__failing-query')->assertServerError();

    $lines = array_values(array_filter(explode("\n", File::get($this->logPath))));

    /** @var array{message: string, context: array{sql: string, code: string, connection: string}} $entry */
    $entry = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);

    expect($entry['message'])->toBe('database.query_failed')
        ->and($entry['context']['sql'])->toBe('select * from table_that_does_not_exist where email = ? and phone = ?')
        ->and($entry['context']['code'])->toBe('42P01')
        ->and($entry['context']['connection'])->toBe('pgsql');
});
