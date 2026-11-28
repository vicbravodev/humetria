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
        'home', // @pest-mutate-ignore: RemoveArrayItem
        'privacy-notice', // @pest-mutate-ignore: RemoveArrayItem
        'roadmap', // @pest-mutate-ignore: RemoveArrayItem
        'tools.nom_035', // @pest-mutate-ignore: RemoveArrayItem
        'tools.nom_037', // @pest-mutate-ignore: RemoveArrayItem
        'tools.ley_silla', // @pest-mutate-ignore: RemoveArrayItem
        'tools.complaints', // @pest-mutate-ignore: RemoveArrayItem
        'tools.compensation', // @pest-mutate-ignore: RemoveArrayItem
        'tools.attendance', // @pest-mutate-ignore: RemoveArrayItem
        'tools.leave', // @pest-mutate-ignore: RemoveArrayItem
        'tools.org_chart', // @pest-mutate-ignore: RemoveArrayItem
        'tools.engagement', // @pest-mutate-ignore: RemoveArrayItem
        'guides.workday-2027', // @pest-mutate-ignore: RemoveArrayItem
        'blog.index', // @pest-mutate-ignore: RemoveArrayItem
    ];

    /** Routes with a parameter, so they cannot be listed in the static sitemap: the blog lists its articles in its own. */
    public const array WITH_PARAMETERS = ['blog.show']; // @pest-mutate-ignore: RemoveArrayItem

    public static function includes(?string $routeName): bool
    {
        return $routeName !== null && (in_array($routeName, self::ROUTES, true) || in_array($routeName, self::WITH_PARAMETERS, true));
    }
}
