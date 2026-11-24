<?php

declare(strict_types=1);

namespace App\Modules\Blog\Support;

/**
 * The tools an article can be about (`tool:` in its front matter): the name the public pages give it, the family whose
 * color dresses it (the same three as resources/js/components/landing/landing-families.ts) and its page.
 */
final class BlogTools
{
    /** @var array<string, array{name: string, family: string, route: string}> */
    private const array TOOLS = [
        'nom_035' => ['name' => 'NOM-035', 'family' => 'compliance', 'route' => 'tools.nom_035'],
        'nom_037' => ['name' => 'NOM-037', 'family' => 'compliance', 'route' => 'tools.nom_037'],
        'ley_silla' => ['name' => 'Ley Silla', 'family' => 'compliance', 'route' => 'tools.ley_silla'],
        'attendance' => ['name' => 'Registro de jornada', 'family' => 'compliance', 'route' => 'tools.attendance'],
        'complaints' => ['name' => 'Buzón de quejas', 'family' => 'listening', 'route' => 'tools.complaints'],
        'engagement' => ['name' => 'Clima y pulso', 'family' => 'listening', 'route' => 'tools.engagement'],
        'leave' => ['name' => 'Vacaciones y permisos', 'family' => 'operations', 'route' => 'tools.leave'],
        'org_chart' => ['name' => 'Organigrama', 'family' => 'operations', 'route' => 'tools.org_chart'],
        'compensation' => ['name' => 'Compensación', 'family' => 'operations', 'route' => 'tools.compensation'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::TOOLS);
    }

    /** @return array{name: string, family: string, route: string}|null */
    public static function find(mixed $key): ?array
    {
        return is_string($key) ? (self::TOOLS[$key] ?? null) : null;
    }
}
