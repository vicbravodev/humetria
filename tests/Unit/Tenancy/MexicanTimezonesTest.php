<?php

declare(strict_types=1);

use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Support\MexicanTimezones;
use Carbon\CarbonImmutable;

mutates(MexicanTimezones::class);

it('allows exactly the zones the system database lists for Mexico', function (): void {
    expect(MexicanTimezones::all())->toBe(DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'MX'))
        ->and(MexicanTimezones::allows('America/Tijuana'))->toBeTrue()
        ->and(MexicanTimezones::allows('America/Los_Angeles'))->toBeFalse()
        ->and(MexicanTimezones::allows('UTC'))->toBeFalse()
        ->and(MexicanTimezones::allows('america/tijuana'))->toBeFalse();
});

it('suggests the zone of a state with only one', function (MexicanState $state, string $zone): void {
    expect(MexicanTimezones::suggest($state, 'Cualquiera'))->toBe($zone);
})->with([
    'Quintana Roo, Zona Sureste all year' => [MexicanState::QuintanaRoo, 'America/Cancun'],
    'Sonora, no daylight saving' => [MexicanState::Sonora, 'America/Hermosillo'],
    'Baja California, the whole state' => [MexicanState::BajaCalifornia, 'America/Tijuana'],
    'Baja California Sur' => [MexicanState::BajaCaliforniaSur, 'America/Mazatlan'],
    'Sinaloa' => [MexicanState::Sinaloa, 'America/Mazatlan'],
    'Yucatán' => [MexicanState::Yucatan, 'America/Merida'],
    'Campeche' => [MexicanState::Campeche, 'America/Merida'],
    'Nuevo León' => [MexicanState::NuevoLeon, 'America/Monterrey'],
    'Durango' => [MexicanState::Durango, 'America/Monterrey'],
    'Querétaro' => [MexicanState::Queretaro, 'America/Mexico_City'],
    'Ciudad de México' => [MexicanState::MexicoCity, 'America/Mexico_City'],
    'Chihuahua, away from the border' => [MexicanState::Chihuahua, 'America/Chihuahua'],
]);

it('suggests the zone of the municipality in a state with more than one, whatever its case or accents', function (MexicanState $state, string $municipality, string $zone): void {
    expect(MexicanTimezones::suggest($state, $municipality))->toBe($zone);
})->with([
    'Juárez, Chihuahua' => [MexicanState::Chihuahua, 'Juárez', 'America/Ciudad_Juarez'],
    'Juarez, unaccented and in capitals' => [MexicanState::Chihuahua, '  JUAREZ ', 'America/Ciudad_Juarez'],
    'Ojinaga' => [MexicanState::Chihuahua, 'Ojinaga', 'America/Ojinaga'],
    'Piedras Negras, Coahuila' => [MexicanState::Coahuila, 'Piedras Negras', 'America/Matamoros'],
    'Saltillo, Coahuila' => [MexicanState::Coahuila, 'Saltillo', 'America/Monterrey'],
    'Anáhuac, Nuevo León' => [MexicanState::NuevoLeon, 'Anáhuac', 'America/Matamoros'],
    'Reynosa, Tamaulipas' => [MexicanState::Tamaulipas, 'Reynosa', 'America/Matamoros'],
    'Tampico, Tamaulipas' => [MexicanState::Tamaulipas, 'Tampico', 'America/Monterrey'],
    'Bahía de Banderas, Nayarit' => [MexicanState::Nayarit, 'Bahía de Banderas', 'America/Bahia_Banderas'],
    'Tepic, Nayarit' => [MexicanState::Nayarit, 'Tepic', 'America/Mazatlan'],
    'a Juárez outside Chihuahua is not the border one' => [MexicanState::NuevoLeon, 'Juárez', 'America/Monterrey'],
]);

/*
 * The exceptions by municipality, as the Ley de los Husos Horarios en los Estados Unidos Mexicanos gives them (DOF 28-10-2022,
 * reformed DOF 29-11-2022): art. 3, fr. II (Bahía de Banderas in the Zona Centro) and art. 5, fr. I to III (the border
 * seasonal time, from 02:00 of the second Sunday of March to 02:00 of the first Sunday of November: in 2026, 8 March and
 * 1 November). Each: state, municipality as typed, zone, and its offset in hours in winter and in summer.
 */
dataset('border municipalities', [
    'Acuña, Coahuila' => [MexicanState::Coahuila, 'Acuña', 'America/Matamoros', -6, -5],
    'Allende, Coahuila' => [MexicanState::Coahuila, 'Allende', 'America/Matamoros', -6, -5],
    'Guerrero, Coahuila' => [MexicanState::Coahuila, 'Guerrero', 'America/Matamoros', -6, -5],
    'Hidalgo, Coahuila' => [MexicanState::Coahuila, 'Hidalgo', 'America/Matamoros', -6, -5],
    'Jiménez, Coahuila' => [MexicanState::Coahuila, 'Jiménez', 'America/Matamoros', -6, -5],
    'Morelos, Coahuila' => [MexicanState::Coahuila, 'Morelos', 'America/Matamoros', -6, -5],
    'Nava, Coahuila' => [MexicanState::Coahuila, 'Nava', 'America/Matamoros', -6, -5],
    'Ocampo, Coahuila' => [MexicanState::Coahuila, 'Ocampo', 'America/Matamoros', -6, -5],
    'Piedras Negras, Coahuila' => [MexicanState::Coahuila, 'Piedras Negras', 'America/Matamoros', -6, -5],
    'Villa Unión, Coahuila' => [MexicanState::Coahuila, 'Villa Unión', 'America/Matamoros', -6, -5],
    'Zaragoza, Coahuila' => [MexicanState::Coahuila, 'Zaragoza', 'America/Matamoros', -6, -5],
    'Anáhuac, Nuevo León' => [MexicanState::NuevoLeon, 'Anáhuac', 'America/Matamoros', -6, -5],
    'Nuevo Laredo, Tamaulipas' => [MexicanState::Tamaulipas, 'Nuevo Laredo', 'America/Matamoros', -6, -5],
    'Guerrero, Tamaulipas' => [MexicanState::Tamaulipas, 'Guerrero', 'America/Matamoros', -6, -5],
    'Mier, Tamaulipas' => [MexicanState::Tamaulipas, 'Mier', 'America/Matamoros', -6, -5],
    'Miguel Alemán, Tamaulipas' => [MexicanState::Tamaulipas, 'Miguel Alemán', 'America/Matamoros', -6, -5],
    'Camargo, Tamaulipas' => [MexicanState::Tamaulipas, 'Camargo', 'America/Matamoros', -6, -5],
    'Gustavo Díaz Ordaz, Tamaulipas' => [MexicanState::Tamaulipas, 'Gustavo Díaz Ordaz', 'America/Matamoros', -6, -5],
    'Reynosa, Tamaulipas' => [MexicanState::Tamaulipas, 'Reynosa', 'America/Matamoros', -6, -5],
    'Río Bravo, Tamaulipas' => [MexicanState::Tamaulipas, 'Río Bravo', 'America/Matamoros', -6, -5],
    'Valle Hermoso, Tamaulipas' => [MexicanState::Tamaulipas, 'Valle Hermoso', 'America/Matamoros', -6, -5],
    'Matamoros, Tamaulipas' => [MexicanState::Tamaulipas, 'Matamoros', 'America/Matamoros', -6, -5],
    'Coyame del Sotol, Chihuahua' => [MexicanState::Chihuahua, 'Coyame del Sotol', 'America/Ojinaga', -6, -5],
    'Ojinaga, Chihuahua' => [MexicanState::Chihuahua, 'Ojinaga', 'America/Ojinaga', -6, -5],
    'Manuel Benavides, Chihuahua' => [MexicanState::Chihuahua, 'Manuel Benavides', 'America/Ojinaga', -6, -5],
    'Janos, Chihuahua' => [MexicanState::Chihuahua, 'Janos', 'America/Ciudad_Juarez', -7, -6],
    'Ascensión, Chihuahua' => [MexicanState::Chihuahua, 'Ascensión', 'America/Ciudad_Juarez', -7, -6],
    'Juárez, Chihuahua' => [MexicanState::Chihuahua, 'Juárez', 'America/Ciudad_Juarez', -7, -6],
    'Praxedis G. Guerrero, Chihuahua' => [MexicanState::Chihuahua, 'Práxedis G. Guerrero', 'America/Ciudad_Juarez', -7, -6],
    'Guadalupe, Chihuahua' => [MexicanState::Chihuahua, 'Guadalupe', 'America/Ciudad_Juarez', -7, -6],
    'Tijuana, the whole of Baja California' => [MexicanState::BajaCalifornia, 'Tijuana', 'America/Tijuana', -8, -7],
    'San Quintín, the newest of Baja California' => [MexicanState::BajaCalifornia, 'San Quintín', 'America/Tijuana', -8, -7],
    'Bahía de Banderas, Nayarit, Centro all year' => [MexicanState::Nayarit, 'Bahía de Banderas', 'America/Bahia_Banderas', -6, -6],
]);

it('suggests for each exception of the law its zone, whose offset changes on 8 March and 1 November 2026', function (MexicanState $state, string $municipality, string $zone, int $winter, int $summer): void {
    $suggested = MexicanTimezones::suggest($state, $municipality);
    $offset = fn (string $at): int => intdiv(CarbonImmutable::parse($at, 'UTC')->setTimezone($suggested)->getOffset(), 3600);

    expect($suggested)->toBe($zone)
        ->and($offset('2026-03-07 12:00'))->toBe($winter)
        ->and($offset('2026-03-09 12:00'))->toBe($summer)
        ->and($offset('2026-10-31 12:00'))->toBe($summer)
        ->and($offset('2026-11-02 12:00'))->toBe($winter);
})->with('border municipalities');

it('changes the border time at 02:00 local, as the law says', function (string $zone, string $march, string $november, int $winter, int $summer): void {
    $offset = fn (string $at): int => intdiv(CarbonImmutable::parse($at, 'UTC')->setTimezone($zone)->getOffset(), 3600);

    expect($offset($march.':59'))->toBe($winter)
        ->and($offset(CarbonImmutable::parse($march.':59', 'UTC')->addSecond()->toDateTimeString()))->toBe($summer)
        ->and($offset($november.':59'))->toBe($summer)
        ->and($offset(CarbonImmutable::parse($november.':59', 'UTC')->addSecond()->toDateTimeString()))->toBe($winter);
})->with([
    // 01:59:59 local, in UTC: 02:00 winter time is 08:00Z at UTC−6; 02:00 summer time is 07:00Z at UTC−5.
    'Matamoros' => ['America/Matamoros', '2026-03-08 07:59', '2026-11-01 06:59', -6, -5],
    'Ojinaga' => ['America/Ojinaga', '2026-03-08 07:59', '2026-11-01 06:59', -6, -5],
    'Ciudad Juárez' => ['America/Ciudad_Juarez', '2026-03-08 08:59', '2026-11-01 07:59', -7, -6],
    'Tijuana' => ['America/Tijuana', '2026-03-08 09:59', '2026-11-01 08:59', -8, -7],
]);

it('lists exactly the municipalities of the law, no more and no fewer', function (): void {
    $municipalities = array_map(fn (array $zones): array => array_keys($zones), MexicanTimezones::rules()['municipalities']);

    expect($municipalities)->toEqualCanonicalizing([
        'CHH' => ['janos', 'ascension', 'juarez', 'praxedis g. guerrero', 'guadalupe', 'coyame del sotol', 'ojinaga', 'manuel benavides'],
        'COA' => ['acuna', 'allende', 'guerrero', 'hidalgo', 'jimenez', 'morelos', 'nava', 'ocampo', 'piedras negras', 'villa union', 'zaragoza'],
        'NLE' => ['anahuac'],
        'TAM' => ['nuevo laredo', 'guerrero', 'mier', 'miguel aleman', 'camargo', 'gustavo diaz ordaz', 'reynosa', 'rio bravo', 'valle hermoso', 'matamoros'],
        'NAY' => ['bahia de banderas'],
    ]);
});

it('keeps the zone of the state where the law makes no exception', function (MexicanState $state, string $municipality, string $zone): void {
    expect(MexicanTimezones::suggest($state, $municipality))->toBe($zone);
})->with([
    'a state without municipality' => [MexicanState::Chihuahua, '', 'America/Chihuahua'],
    'only spaces' => [MexicanState::Coahuila, '   ', 'America/Monterrey'],
    'Nogales, Sonora: on the border but without seasonal time' => [MexicanState::Sonora, 'Nogales', 'America/Hermosillo'],
    'Guadalupe y Calvo is not Guadalupe' => [MexicanState::Chihuahua, 'Guadalupe y Calvo', 'America/Chihuahua'],
    'Monterrey, Nuevo León' => [MexicanState::NuevoLeon, 'Monterrey', 'America/Monterrey'],
    'a border name in a state without exceptions' => [MexicanState::Jalisco, 'Reynosa', 'America/Mexico_City'],
    'Guerrero of Coahuila typed in Durango' => [MexicanState::Durango, 'Guerrero', 'America/Monterrey'],
    'mixed case and accents' => [MexicanState::Tamaulipas, 'gUSTAVO DÍAZ ordaz', 'America/Matamoros'],
]);

it('only ever suggests an allowed zone', function (): void {
    foreach (MexicanState::cases() as $state) {
        expect(MexicanTimezones::allows(MexicanTimezones::suggest($state, '')))->toBeTrue();
    }

    foreach (MexicanTimezones::rules()['municipalities'] as $municipalities) {
        foreach ($municipalities as $zone) {
            expect(MexicanTimezones::allows($zone))->toBeTrue();
        }
    }
});

it('names a zone with its offset at an instant and its city, so the shift of the border shows', function (): void {
    $winter = CarbonImmutable::parse('2026-12-01 12:00', 'UTC');
    $summer = CarbonImmutable::parse('2026-07-01 12:00', 'UTC');

    expect(MexicanTimezones::label('America/Hermosillo', $winter))->toBe('Hora del Pacífico, UTC−7 · Hermosillo')
        ->and(MexicanTimezones::label('America/Cancun', $winter))->toBe('Hora del Sureste, UTC−5 · Cancún')
        ->and(MexicanTimezones::label('America/Mexico_City', $summer))->toBe('Hora del Centro, UTC−6 · Ciudad de México')
        ->and(MexicanTimezones::label('America/Tijuana', $winter))->toBe('Hora del Noroeste, UTC−8 · Tijuana')
        ->and(MexicanTimezones::label('America/Tijuana', $summer))->toBe('Hora del Noroeste, UTC−7 · Tijuana');
});

it('gives the options to choose from, each allowed zone once with its label', function (): void {
    $options = MexicanTimezones::options(CarbonImmutable::parse('2026-12-01 12:00', 'UTC'));

    expect(array_column($options, 'value'))->toEqualCanonicalizing(MexicanTimezones::all())
        ->and($options[0])->toBe(['value' => 'America/Cancun', 'label' => 'Hora del Sureste, UTC−5 · Cancún'])
        ->and(end($options)['value'])->toBe('America/Tijuana');
});
