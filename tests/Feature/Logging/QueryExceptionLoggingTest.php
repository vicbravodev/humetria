<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Exception\TransportException;

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

    $lastLine = Str::afterLast(trim(File::get($this->logPath)), "\n");

    expect(json_decode($lastLine, true, flags: JSON_THROW_ON_ERROR))
        ->toHaveKey('message', 'database.query_failed')
        ->toHaveKey('context.sql', 'select * from table_that_does_not_exist where email = ? and phone = ?')
        ->toHaveKey('context.code', '42P01')
        ->toHaveKey('context.connection', 'pgsql');
});

it('reports a failed email by its class and code, never with the recipient its message quotes', function (): void {
    report(new TransportException('Expected response code "250" but got code "550", with message "550 <ana.secreta@empresa.mx>: Recipient address rejected".', 550));

    $log = File::get($this->logPath);
    $lastLine = Str::afterLast(trim($log), "\n");

    expect($log)->not->toContain('ana.secreta@empresa.mx')
        ->and(json_decode($lastLine, true, flags: JSON_THROW_ON_ERROR))
        ->toHaveKey('message', 'mail.send_failed')
        ->toHaveKey('context', ['exception' => 'TransportException', 'code' => '550']);
});
