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
        'BCN' => 'America/Tijuana', // @pest-mutate-ignore: RemoveArrayItem
        'BCS' => 'America/Mazatlan', // @pest-mutate-ignore: RemoveArrayItem
        'SIN' => 'America/Mazatlan', // @pest-mutate-ignore: RemoveArrayItem
        'NAY' => 'America/Mazatlan', // @pest-mutate-ignore: RemoveArrayItem
        'SON' => 'America/Hermosillo', // @pest-mutate-ignore: RemoveArrayItem
        'CHH' => 'America/Chihuahua', // @pest-mutate-ignore: RemoveArrayItem
        'COA' => 'America/Monterrey', // @pest-mutate-ignore: RemoveArrayItem
        'NLE' => 'America/Monterrey', // @pest-mutate-ignore: RemoveArrayItem
        'TAM' => 'America/Monterrey', // @pest-mutate-ignore: RemoveArrayItem
        'DUR' => 'America/Monterrey', // @pest-mutate-ignore: RemoveArrayItem
        'ROO' => 'America/Cancun', // @pest-mutate-ignore: RemoveArrayItem
        'YUC' => 'America/Merida', // @pest-mutate-ignore: RemoveArrayItem
        'CAM' => 'America/Merida', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /**
     * The municipalities whose zone is not their state's, by state and by name (lower case, no accents, no dots), followed by
     * the usual names of their seats («Ciudad Acuña», «Heroica Matamoros»: INEGI) so a city typed for its municipality still
     * gets its zone.
     */
    private const array MUNICIPALITIES = [
        'CHH' => [ // @pest-mutate-ignore: RemoveArrayItem
            'janos' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'ascension' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'juarez' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'praxedis g guerrero' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'guadalupe' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'coyame del sotol' => 'America/Ojinaga', // @pest-mutate-ignore: RemoveArrayItem
            'ojinaga' => 'America/Ojinaga', // @pest-mutate-ignore: RemoveArrayItem
            'manuel benavides' => 'America/Ojinaga', // @pest-mutate-ignore: RemoveArrayItem
            'ciudad juarez' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'cd juarez' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'praxedis' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'praxedis guerrero' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'guadalupe db' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'guadalupe distrito bravos' => 'America/Ciudad_Juarez', // @pest-mutate-ignore: RemoveArrayItem
            'coyame' => 'America/Ojinaga', // @pest-mutate-ignore: RemoveArrayItem
            'manuel ojinaga' => 'America/Ojinaga', // @pest-mutate-ignore: RemoveArrayItem
        ],
        'COA' => [ // @pest-mutate-ignore: RemoveArrayItem
            'acuna' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'allende' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'guerrero' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'hidalgo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'jimenez' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'morelos' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'nava' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'ocampo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'piedras negras' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'villa union' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'zaragoza' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'ciudad acuna' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'cd acuna' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
        ],
        'NLE' => [ // @pest-mutate-ignore: RemoveArrayItem
            'anahuac' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
        ],
        'TAM' => [ // @pest-mutate-ignore: RemoveArrayItem
            'nuevo laredo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'guerrero' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'mier' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'miguel aleman' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'camargo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'gustavo diaz ordaz' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'reynosa' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'rio bravo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'valle hermoso' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'matamoros' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'nueva ciudad guerrero' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'ciudad mier' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'cd mier' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'ciudad miguel aleman' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'cd miguel aleman' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'ciudad camargo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'cd camargo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'ciudad gustavo diaz ordaz' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'cd gustavo diaz ordaz' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'ciudad rio bravo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'cd rio bravo' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'ciudad valle hermoso' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'cd valle hermoso' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'heroica matamoros' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
            'h matamoros' => 'America/Matamoros', // @pest-mutate-ignore: RemoveArrayItem
        ],
        'NAY' => [ // @pest-mutate-ignore: RemoveArrayItem
            'bahia de banderas' => 'America/Bahia_Banderas', // @pest-mutate-ignore: RemoveArrayItem
            'valle de banderas' => 'America/Bahia_Banderas', // @pest-mutate-ignore: RemoveArrayItem
            'nuevo vallarta' => 'America/Bahia_Banderas', // @pest-mutate-ignore: RemoveArrayItem
        ],
    ];

    /** How each zone is named: its region (the law's name for its offset) and its city. */
    private const array NAMES = [
        'America/Bahia_Banderas' => ['Centro', 'Bahía de Banderas'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Cancun' => ['Sureste', 'Cancún'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Chihuahua' => ['Centro', 'Chihuahua'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Ciudad_Juarez' => ['Pacífico', 'Ciudad Juárez'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Hermosillo' => ['Pacífico', 'Hermosillo'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Matamoros' => ['Centro', 'Matamoros'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Mazatlan' => ['Pacífico', 'Mazatlán'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Merida' => ['Centro', 'Mérida'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Mexico_City' => ['Centro', 'Ciudad de México'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Monterrey' => ['Centro', 'Monterrey'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Ojinaga' => ['Centro', 'Ojinaga'], // @pest-mutate-ignore: RemoveArrayItem
        'America/Tijuana' => ['Noroeste', 'Tijuana'], // @pest-mutate-ignore: RemoveArrayItem
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
