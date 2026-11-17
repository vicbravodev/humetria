<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

use App\Modules\Tenancy\Enums\MexicanState;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Str;

/**
 * The time zones a work center of Mexico may have (design: docs/superpowers/specs/2026-11-17-timezones-design.md): the IANA
 * zones the system's database lists for Mexico, never a rule of our own. The suggestion comes from the state and, where a
 * state has more than one zone, from the municipality: the tables below are data, not rules. Source: the Ley de los Husos
 * Horarios en los Estados Unidos Mexicanos (DOF 28-10-2022, last reform DOF 29-11-2022; consolidated text at
 * https://www.diputados.gob.mx/LeyesBiblio/pdf/LHHEUM.pdf, checked 2026-11-17): art. 3 for the zones and Bahía de Banderas,
 * art. 5 for the border municipalities with the seasonal time, the same lists tzdata gives America/Matamoros, Ojinaga and
 * Ciudad_Juarez. Whatever the table says, tzdata keeps each zone's rules.
 */
final class MexicanTimezones
{
    /** The zone of each state, by its ISO code; a state not here is in America/Mexico_City. */
    private const array STATES = [
        'BCN' => 'America/Tijuana',
        'BCS' => 'America/Mazatlan',
        'SIN' => 'America/Mazatlan',
        'NAY' => 'America/Mazatlan',
        'SON' => 'America/Hermosillo',
        'CHH' => 'America/Chihuahua',
        'COA' => 'America/Monterrey',
        'NLE' => 'America/Monterrey',
        'TAM' => 'America/Monterrey',
        'DUR' => 'America/Monterrey',
        'ROO' => 'America/Cancun',
        'YUC' => 'America/Merida',
        'CAM' => 'America/Merida',
    ];

    /**
     * The municipalities whose zone is not their state's, by state and by name (lower case, no accents, no dots), followed by
     * the usual names of their seats («Ciudad Acuña», «Heroica Matamoros»: INEGI) so a city typed for its municipality still
     * gets its zone.
     */
    private const array MUNICIPALITIES = [
        'CHH' => [
            'janos' => 'America/Ciudad_Juarez',
            'ascension' => 'America/Ciudad_Juarez',
            'juarez' => 'America/Ciudad_Juarez',
            'praxedis g guerrero' => 'America/Ciudad_Juarez',
            'guadalupe' => 'America/Ciudad_Juarez',
            'coyame del sotol' => 'America/Ojinaga',
            'ojinaga' => 'America/Ojinaga',
            'manuel benavides' => 'America/Ojinaga',
            'ciudad juarez' => 'America/Ciudad_Juarez',
            'cd juarez' => 'America/Ciudad_Juarez',
            'praxedis' => 'America/Ciudad_Juarez',
            'praxedis guerrero' => 'America/Ciudad_Juarez',
            'guadalupe db' => 'America/Ciudad_Juarez',
            'guadalupe distrito bravos' => 'America/Ciudad_Juarez',
            'coyame' => 'America/Ojinaga',
            'manuel ojinaga' => 'America/Ojinaga',
        ],
        'COA' => [
            'acuna' => 'America/Matamoros',
            'allende' => 'America/Matamoros',
            'guerrero' => 'America/Matamoros',
            'hidalgo' => 'America/Matamoros',
            'jimenez' => 'America/Matamoros',
            'morelos' => 'America/Matamoros',
            'nava' => 'America/Matamoros',
            'ocampo' => 'America/Matamoros',
            'piedras negras' => 'America/Matamoros',
            'villa union' => 'America/Matamoros',
            'zaragoza' => 'America/Matamoros',
            'ciudad acuna' => 'America/Matamoros',
            'cd acuna' => 'America/Matamoros',
        ],
        'NLE' => [
            'anahuac' => 'America/Matamoros',
        ],
        'TAM' => [
            'nuevo laredo' => 'America/Matamoros',
            'guerrero' => 'America/Matamoros',
            'mier' => 'America/Matamoros',
            'miguel aleman' => 'America/Matamoros',
            'camargo' => 'America/Matamoros',
            'gustavo diaz ordaz' => 'America/Matamoros',
            'reynosa' => 'America/Matamoros',
            'rio bravo' => 'America/Matamoros',
            'valle hermoso' => 'America/Matamoros',
            'matamoros' => 'America/Matamoros',
            'nueva ciudad guerrero' => 'America/Matamoros',
            'ciudad mier' => 'America/Matamoros',
            'cd mier' => 'America/Matamoros',
            'ciudad miguel aleman' => 'America/Matamoros',
            'cd miguel aleman' => 'America/Matamoros',
            'ciudad camargo' => 'America/Matamoros',
            'cd camargo' => 'America/Matamoros',
            'ciudad gustavo diaz ordaz' => 'America/Matamoros',
            'cd gustavo diaz ordaz' => 'America/Matamoros',
            'ciudad rio bravo' => 'America/Matamoros',
            'cd rio bravo' => 'America/Matamoros',
            'ciudad valle hermoso' => 'America/Matamoros',
            'cd valle hermoso' => 'America/Matamoros',
            'heroica matamoros' => 'America/Matamoros',
            'h matamoros' => 'America/Matamoros',
        ],
        'NAY' => [
            'bahia de banderas' => 'America/Bahia_Banderas',
            'valle de banderas' => 'America/Bahia_Banderas',
            'nuevo vallarta' => 'America/Bahia_Banderas',
        ],
    ];

    /** How each zone is named: its region (the law's name for its offset) and its city. */
    private const array NAMES = [
        'America/Bahia_Banderas' => ['Centro', 'Bahía de Banderas'],
        'America/Cancun' => ['Sureste', 'Cancún'],
        'America/Chihuahua' => ['Centro', 'Chihuahua'],
        'America/Ciudad_Juarez' => ['Pacífico', 'Ciudad Juárez'],
        'America/Hermosillo' => ['Pacífico', 'Hermosillo'],
        'America/Matamoros' => ['Centro', 'Matamoros'],
        'America/Mazatlan' => ['Pacífico', 'Mazatlán'],
        'America/Merida' => ['Centro', 'Mérida'],
        'America/Mexico_City' => ['Centro', 'Ciudad de México'],
        'America/Monterrey' => ['Centro', 'Monterrey'],
        'America/Ojinaga' => ['Centro', 'Ojinaga'],
        'America/Tijuana' => ['Noroeste', 'Tijuana'],
    ];

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::PER_COUNTRY, 'MX');
    }

    public static function allows(string $zone): bool
    {
        return in_array($zone, self::all(), true);
    }

    public static function suggest(MexicanState $state, string $municipality): string
    {
        return self::MUNICIPALITIES[$state->value][self::normalize($municipality)] ?? self::STATES[$state->value] ?? 'America/Mexico_City';
    }

    /**
     * The tables of suggest(), for a form to recompute the zone as the state or the municipality change.
     *
     * @return array{states: array<string, string>, municipalities: array<string, array<string, string>>, fallback: string}
     */
    public static function rules(): array
    {
        return ['states' => self::STATES, 'municipalities' => self::MUNICIPALITIES, 'fallback' => 'America/Mexico_City'];
    }

    /** «Hora del Pacífico, UTC−7 · Hermosillo»: the offset at `$at`, so a border zone shows its season. */
    public static function label(string $zone, CarbonImmutable $at): string
    {
        [$region, $city] = self::NAMES[$zone] ?? ['', $zone];
        $offset = intdiv($at->setTimezone($zone)->getOffset(), 60);
        $hours = intdiv(abs($offset), 60);
        $minutes = abs($offset) % 60;
        $utc = 'UTC'.($offset < 0 ? '−' : '+').$hours.($minutes === 0 ? '' : sprintf(':%02d', $minutes));

        return $region === '' ? "{$utc} · {$city}" : "Hora del {$region}, {$utc} · {$city}";
    }

    /**
     * Every allowed zone to choose from, east to west at `$at`.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(CarbonImmutable $at): array
    {
        $zones = self::all();
        usort($zones, fn (string $a, string $b): int => [$at->setTimezone($b)->getOffset(), self::label($a, $at)] <=> [$at->setTimezone($a)->getOffset(), self::label($b, $at)]);

        return array_map(fn (string $zone): array => ['value' => $zone, 'label' => self::label($zone, $at)], $zones);
    }

    private static function normalize(string $municipality): string
    {
        return Str::squish(str_replace('.', '', Str::lower(Str::ascii($municipality))));
    }
}
