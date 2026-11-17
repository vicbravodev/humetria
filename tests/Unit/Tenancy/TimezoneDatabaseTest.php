<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;

/*
 * The zones' rules are the system's (tzdata in PHP and ICU), never ours: this fails when the container brings old rules,
 * so the image is rebuilt before a mark is shown at the wrong hour (design §4, phase 5).
 */
it('knows the rules of the Mexican zones of 2026', function (string $utc, string $zone, string $local): void {
    expect(CarbonImmutable::parse($utc, 'UTC')->setTimezone($zone)->format('Y-m-d H:i P'))->toBe($local);
})->with([
    'Cancún, UTC−5 all year' => ['2026-12-01 11:00', 'America/Cancun', '2026-12-01 06:00 -05:00'],
    'Mexico City, no daylight saving since 2022' => ['2026-07-01 12:00', 'America/Mexico_City', '2026-07-01 06:00 -06:00'],
    'Hermosillo, UTC−7 all year' => ['2026-07-01 13:00', 'America/Hermosillo', '2026-07-01 06:00 -07:00'],
    'Chihuahua, UTC−6 since 2022' => ['2026-07-01 12:00', 'America/Chihuahua', '2026-07-01 06:00 -06:00'],
    'Tijuana, the last hour of summer time' => ['2026-11-01 08:59', 'America/Tijuana', '2026-11-01 01:59 -07:00'],
    'Tijuana, the repeated hour after the change of 1 November' => ['2026-11-01 09:30', 'America/Tijuana', '2026-11-01 01:30 -08:00'],
    'Tijuana, before the change of 8 March' => ['2026-03-08 09:59', 'America/Tijuana', '2026-03-08 01:59 -08:00'],
    'Tijuana, after it' => ['2026-03-08 10:00', 'America/Tijuana', '2026-03-08 03:00 -07:00'],
    'Ciudad Juárez keeps the border season' => ['2026-07-01 12:00', 'America/Ciudad_Juarez', '2026-07-01 06:00 -06:00'],
    'Matamoros keeps the border season' => ['2026-07-01 11:00', 'America/Matamoros', '2026-07-01 06:00 -05:00'],
]);
