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
