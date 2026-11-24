<?php

declare(strict_types=1);

namespace App\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * The only pages rendered in Node (server-side rendering), deny by default: a route name and the component it serves.
 * Everything else, whoever asks, is rendered by the browser as before. Not derived from IndexablePages: a page may be
 * indexable without being rendered in Node, and the other way round never.
 */
final class SsrPages
{
    /** @var array<string, string> route name => component */
    public const array ALLOWED = [
        'home' => 'landing',
        'privacy-notice' => 'privacy-notice',
        'tools.nom_035' => 'tool',
        'tools.nom_037' => 'tool',
        'tools.ley_silla' => 'tool',
        'tools.complaints' => 'tool',
        'tools.compensation' => 'tool',
        'tools.attendance' => 'tool',
        'tools.leave' => 'tool',
        'tools.org_chart' => 'tool',
        'tools.engagement' => 'tool',
        'guides.workday-2027' => 'guide',
        'blog.index' => 'blog/index',
        'blog.show' => 'blog/show',
    ];

    /** Set for a few seconds after Node could not be reached, so a stuck Node does not cost every visit its timeout. */
    public const string CIRCUIT_KEY = 'ssr:circuit-open';

    public const int CIRCUIT_SECONDS = 30;

    /**
     * @param  array<string, mixed>  $page  the Inertia page about to be rendered
     */
    public static function allows(Request $request, array $page): bool
    {
        if (! config('inertia.ssr.enabled') || $request->user() !== null || Cache::has(self::CIRCUIT_KEY)) {
            return false;
        }

        $routeName = $request->route()?->getName();

        return is_string($routeName)
            && ($page['component'] ?? null) === (self::ALLOWED[$routeName] ?? false);
    }
}
