<?php

declare(strict_types=1);

use App\Console\Commands\UpdateGeoIp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

mutates(UpdateGeoIp::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-03 10:00 UTC');
    $this->database = storage_path('framework/testing/geoip/dbip-city-lite.mmdb');
    config(['services.geoip.database' => $this->database]);
    @unlink($this->database);
});

afterEach(function (): void {
    @unlink($this->database);
});

it('downloads this month\'s DB-IP city database and leaves it unzipped where the lookups read it', function (): void {
    Http::fake(['download.db-ip.com/free/dbip-city-lite-2026-11.mmdb.gz' => Http::response((string) gzencode('the-database'))]);
    Log::spy();

    $this->artisan('geoip:update')->assertSuccessful();

    expect(file_get_contents($this->database))->toBe('the-database');
    Log::shouldHaveReceived('info')->with('geoip.updated', ['month' => '2026-11']);
});

it('takes last month\'s while this month\'s is not out yet', function (): void {
    Http::fake([
        'download.db-ip.com/free/dbip-city-lite-2026-11.mmdb.gz' => Http::response('', 404),
        'download.db-ip.com/free/dbip-city-lite-2026-10.mmdb.gz' => Http::response((string) gzencode('october')),
    ]);

    $this->artisan('geoip:update')->assertSuccessful();

    expect(file_get_contents($this->database))->toBe('october');
});

it('keeps the database it had when neither month can be downloaded', function (): void {
    @mkdir(dirname($this->database), recursive: true);
    file_put_contents($this->database, 'the-old-one');
    Http::fake(['download.db-ip.com/*' => Http::response('', 500)]);
    Log::spy();

    $this->artisan('geoip:update')->assertFailed();

    expect(file_get_contents($this->database))->toBe('the-old-one');
    Log::shouldHaveReceived('warning')->with('geoip.update_failed');
});

it('keeps the database it had when the download is not a gzip', function (): void {
    @mkdir(dirname($this->database), recursive: true);
    file_put_contents($this->database, 'the-old-one');
    Http::fake(['download.db-ip.com/*' => Http::response('<html>not found</html>')]);

    $this->artisan('geoip:update')->assertFailed();

    expect(file_get_contents($this->database))->toBe('the-old-one');
});

it('runs once a month', function (): void {
    $event = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->first(fn ($event): bool => str_contains((string) $event->command, 'geoip:update'));

    expect($event?->expression)->toBe('0 6 5 * *');
});
