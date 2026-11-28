<?php

declare(strict_types=1);

use App\Contracts\Geolocation;
use App\Support\DbIpGeolocation;
use App\Support\RequestOrigin;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;

mutates(RequestOrigin::class, DbIpGeolocation::class);

function placesAt(?string $place): void
{
    app()->instance(Geolocation::class, new readonly class($place) implements Geolocation
    {
        public function __construct(private ?string $place) {}

        public function place(string $ip): ?string
        {
            return $ip === '201.141.1.1' ? $this->place : null;
        }
    });
}

it('says from which browser, system and city a request came, and when, in Mexico City time', function (): void {
    placesAt('Monterrey, Nuevo León');
    $chrome = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36';

    expect(app(RequestOrigin::class)->describe($chrome, '201.141.1.1', CarbonImmutable::parse('2026-10-30 20:02 UTC')))
        ->toBe('Chrome en macOS · Monterrey, Nuevo León · 30 oct, 14:02');
});

it('names the common browsers and systems', function (string $userAgent, string $origin): void {
    placesAt(null);

    expect(app(RequestOrigin::class)->describe($userAgent, '10.0.0.1', CarbonImmutable::parse('2026-10-30 20:02 UTC')))->toBe("{$origin} · 30 oct, 14:02");
})->with([
    'Edge on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 Edg/130.0.0.0', 'Edge en Windows'],
    'Firefox on Linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', 'Firefox en Linux'],
    'Safari on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari en iPhone'],
    'Firefox on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/131.0 Mobile/15E148 Safari/605.1.15', 'Firefox en iPhone'],
    'Chrome on iPhone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/130.0.6723.90 Mobile/15E148 Safari/604.1', 'Chrome en iPhone'],
    'Chrome on Android' => ['Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36', 'Chrome en Android'],
    'Opera on Windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36 OPR/114.0.0.0', 'Opera en Windows'],
    'Safari on iPad' => ['Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari en iPad'],
    'Chrome on ChromeOS' => ['Mozilla/5.0 (X11; CrOS x86_64 14541.0.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36', 'Chrome en ChromeOS'],
    'only the system' => ['SomeBot/1.0 (Windows NT 10.0)', 'Windows'],
    'nothing known' => ['curl/8.4.0', 'Navegador desconocido'],
]);

it('leaves out the place when the address is not found', function (): void {
    placesAt(null);

    expect(app(RequestOrigin::class)->describe('Mozilla/5.0 (X11; Linux x86_64; rv:131.0) Gecko/20100101 Firefox/131.0', '201.141.1.1', CarbonImmutable::parse('2026-10-30 20:02 UTC')))
        ->toBe('Firefox en Linux · 30 oct, 14:02');
});

it('finds no place while the database has not been downloaded', function (): void {
    config(['services.geoip.database' => storage_path('framework/testing/missing.mmdb')]);

    expect(app(DbIpGeolocation::class)->place('201.141.1.1'))->toBeNull();
});

/** A value in the data format of a MaxMind DB file: strings, unsigned integers, lists and maps are all a record needs. */
function mmdbValue(mixed $value): string
{
    return match (true) {
        is_string($value) => chr(0x40 | strlen($value)).$value,
        is_int($value) => chr(0xC0 | strlen(ltrim(pack('N', $value), "\0"))).ltrim(pack('N', $value), "\0"),
        is_array($value) && array_is_list($value) => chr(count($value)).chr(4).implode('', array_map(mmdbValue(...), $value)),
        is_array($value) => chr(0xE0 | count($value)).implode('', array_map(fn (string $key, mixed $item): string => mmdbValue($key).mmdbValue($item), array_keys($value), $value)),
    };
}

/**
 * A DB-IP-shaped database on disk, the one `geoip:update` would download: every address from 128.0.0.0 up has `$record`,
 * the lower half has none.
 */
function geoDatabaseWith(mixed $record): void
{
    $tree = substr(pack('N', 1), 1).substr(pack('N', 1 + 16), 1);
    $metadata = mmdbValue([
        'binary_format_major_version' => 2, 'binary_format_minor_version' => 0, 'build_epoch' => 1_700_000_000,
        'database_type' => 'DBIP-City-Lite', 'description' => ['en' => 'Test'], 'ip_version' => 4,
        'languages' => ['en', 'es'], 'node_count' => 1, 'record_size' => 24,
    ]);
    $path = storage_path('framework/testing/dbip-city.mmdb');
    file_put_contents($path, $tree.str_repeat("\0", 16).mmdbValue($record)."\xAB\xCD\xEFMaxMind.com".$metadata);
    config(['services.geoip.database' => $path]);
}

afterEach(function (): void {
    File::delete(storage_path('framework/testing/dbip-city.mmdb'));
});

it('finds the city and its state in Spanish in the downloaded database', function (): void {
    geoDatabaseWith([
        'city' => ['names' => ['en' => 'Mexico City', 'es' => 'Ciudad de México']],
        'subdivisions' => [['names' => ['en' => 'Mexico City', 'es' => 'Ciudad de México']], ['names' => ['es' => 'Coyoacán']]],
    ]);

    expect(app(DbIpGeolocation::class)->place('201.141.1.1'))->toBe('Ciudad de México, Ciudad de México');
});

it('takes the first subdivision as the state', function (): void {
    geoDatabaseWith([
        'city' => ['names' => ['es' => 'Monterrey']],
        'subdivisions' => [['names' => ['es' => 'Nuevo León']], ['names' => ['es' => 'Área metropolitana']]],
    ]);

    expect(app(DbIpGeolocation::class)->place('201.141.1.1'))->toBe('Monterrey, Nuevo León');
});

it('falls back to the English name when there is no Spanish one', function (): void {
    geoDatabaseWith(['city' => ['names' => ['en' => 'Guadalajara', 'de' => 'Guadalajara (Jalisco)']], 'subdivisions' => [['names' => ['en' => 'Jalisco']]]]);

    expect(app(DbIpGeolocation::class)->place('201.141.1.1'))->toBe('Guadalajara, Jalisco');
});

it('says only the city when the address has no state', function (array $record): void {
    geoDatabaseWith($record);

    expect(app(DbIpGeolocation::class)->place('201.141.1.1'))->toBe('Tijuana');
})->with([
    'no subdivisions' => [['city' => ['names' => ['es' => 'Tijuana']]]],
    'a subdivision without names' => [['city' => ['names' => ['es' => 'Tijuana']], 'subdivisions' => [['iso_code' => 'BC']]]],
    'subdivisions that are not a list' => [['city' => ['names' => ['es' => 'Tijuana']], 'subdivisions' => 'BC']],
]);

it('gives no place without a city, even when the state is known', function (mixed $record): void {
    geoDatabaseWith($record);

    expect(app(DbIpGeolocation::class)->place('201.141.1.1'))->toBeNull();
})->with([
    'no city' => [['subdivisions' => [['names' => ['es' => 'Jalisco']]]]],
    'a city without names' => [['city' => ['geoname_id' => 4005539], 'subdivisions' => [['names' => ['es' => 'Jalisco']]]]],
    'a city that is not a map' => [['city' => 'Zapopan']],
    'a name that is not text' => [['city' => ['names' => ['es' => 4005539]]]],
    'a record that is not a map' => ['Zapopan'],
]);

it('gives no place for an address the database does not have', function (): void {
    geoDatabaseWith(['city' => ['names' => ['es' => 'Monterrey']]]);

    expect(app(DbIpGeolocation::class)->place('10.0.0.1'))->toBeNull()
        ->and(app(DbIpGeolocation::class)->place('201.141.1.1'))->toBe('Monterrey');
});

it('gives no place for a malformed address or a broken file', function (): void {
    geoDatabaseWith(['city' => ['names' => ['es' => 'Monterrey']]]);
    expect(app(DbIpGeolocation::class)->place('not-an-address'))->toBeNull();

    file_put_contents(config()->string('services.geoip.database'), 'not a database');
    expect(app(DbIpGeolocation::class)->place('201.141.1.1'))->toBeNull();
});
