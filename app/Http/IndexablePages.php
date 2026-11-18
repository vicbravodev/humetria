<?php

declare(strict_types=1);

namespace App\Http;

/**
 * The only pages search engines may index. Everything else is `noindex` by default (see SecurityHeaders), so a new
 * route is born hidden and becomes indexable only by being named here.
 */
final class IndexablePages
{
    /** @var list<string> */
    public const array ROUTES = [
        'home',
        'privacy-notice',
        'roadmap',
        'tools.nom_035',
        'tools.nom_037',
        'tools.ley_silla',
        'tools.complaints',
        'tools.compensation',
        'tools.attendance',
        'tools.leave',
        'tools.org_chart',
        'tools.engagement',
        'guides.workday-2027',
    ];

    public static function includes(?string $routeName): bool
    {
        return $routeName !== null && in_array($routeName, self::ROUTES, true);
    }
}
