<?php

declare(strict_types=1);

use App\Contracts\Geolocation;
use App\Support\DbIpGeolocation;
use App\Support\RequestOrigin;
use Carbon\CarbonImmutable;

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
