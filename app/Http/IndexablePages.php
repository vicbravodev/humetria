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
    public const array ROUTES = ['home'];

    public static function includes(?string $routeName): bool
    {
        return $routeName !== null && in_array($routeName, self::ROUTES, true);
    }
}
