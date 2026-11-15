<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Analytics;
use App\Http\IndexablePages;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a nonce-based Content-Security-Policy, the standard security headers and `X-Robots-Tag: noindex, nofollow`
 * (except on an indexable page, see IndexablePages) on every response.
 * Registered first in the global stack so it also covers 404s of unknown URLs, binding 404s, CSRF 419s
 * and Inertia 409s. A header the response already carries is kept: a route may override one on purpose.
 */
final class SecurityHeaders
{
    /**
     * Sonner injects its stylesheet at import time with no nonce hook: an empty <style> first, then its CSS.
     * These hashes allow exactly those two contents and are tied to the sonner version pinned in package.json.
     * Upgrading sonner changes the second one: the browser tests then report the blocked style.
     */
    private const string SONNER_STYLES = "'sha256-47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU=' 'sha256-StEaX+se6YS7pqjzrzMIA0KaX9zF/8zAhvQXZAe5epY='";

    /**
     * The map of a zone (Asistencia, zone-picker-map.tsx) paints OpenStreetMap's raster tiles: images only, from this
     * one origin, and only in the signed-in panel (`/admin`): the public pages and the phone's screens keep no outside
     * origin. The whole panel gets it because Inertia moves between its pages without reloading, so the policy is the
     * one of the page the person came in by. Address search goes through our own server (PlaceController), so
     * connect-src stays 'self'.
     */
    private const string MAP_TILES = 'https://tile.openstreetmap.org';

    /**
     * The origins Google Analytics (gtag.js) needs, by directive. Only on `GET /` for a visitor without a session, with a
     * valid measurement id and consent (Analytics::measurementId): without them the policy is exactly the one of any page.
     * Source: Google's "Content Security Policy" guide for Tag Manager and Google Analytics 4 (the starting list, wildcards
     * included). NOT verified against a browser with a real id: confirm in DebugView and in the console before turning it on.
     *
     * @var array{script: string, img: string, connect: string}
     */
    private const array GOOGLE_ANALYTICS_ORIGINS = [
        'script' => 'https://*.googletagmanager.com',
        'img' => 'https://*.google-analytics.com https://*.googletagmanager.com',
        'connect' => 'https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com',
    ];

    private const string HORIZON_FONTS = 'https://fonts.bunny.net';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $devServer = $this->devServer();

        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->policy($nonce, $devServer, $this->paintsMaps($request), $this->isHorizon($request), Analytics::measurementId($request) !== null),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        if ($this->isDebugExceptionPage($response)) {
            unset($headers['Content-Security-Policy']);
        }

        if (App::isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (! $this->isIndexable($request, $response)) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * `noindex` is the default: a page is indexable only when its route is in IndexablePages, it answered 200 and the
     * app runs in production (staging and local never get indexed).
     */
    private function isIndexable(Request $request, Response $response): bool
    {
        return App::isProduction()
            && $response->getStatusCode() === 200
            && IndexablePages::includes($request->route()?->getName());
    }

    /**
     * Laravel's debug exception page inlines its own styles and scripts without a nonce. It only renders
     * with app.debug on, which is never the case in production, so it goes without a policy.
     */
    private function isDebugExceptionPage(Response $response): bool
    {
        return Config::boolean('app.debug')
            && $response->isServerError()
            && $response instanceof IlluminateResponse
            && $response->exception !== null;
    }

    private function paintsMaps(Request $request): bool
    {
        return $request->user() !== null && $request->is('admin', 'admin/*');
    }

    /**
     * Horizon compiles its Vue templates with `new Function` and ships no build without eval, and its stylesheet
     * imports a font from Bunny. The exception lives only on its path, and only for the superadmin: anyone else gets the strict policy.
     */
    private function isHorizon(Request $request): bool
    {
        $path = Config::string('horizon.path');

        return $request->is($path, $path.'/*') && Gate::allows('viewHorizon');
    }

    private function policy(string $nonce, string $devServer, bool $maps, bool $horizon, bool $analytics): string
    {
        $eval = $horizon ? " 'unsafe-eval'" : '';
        $bunny = $horizon ? ' '.self::HORIZON_FONTS : '';
        $socket = $devServer === '' ? '' : ' '.preg_replace('/^http/', 'ws', $devServer);
        $devServer = $devServer === '' ? '' : ' '.$devServer;
        $google = fn (string $directive): string => $analytics ? ' '.self::GOOGLE_ANALYTICS_ORIGINS[$directive] : '';

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$eval}{$devServer}{$google('script')}",
            "style-src 'self' 'nonce-{$nonce}' ".self::SONNER_STYLES.$bunny.$devServer,
            "img-src 'self' data:".($maps ? ' '.self::MAP_TILES : '').$devServer.$google('img'),
            "font-src 'self'{$bunny}{$devServer}",
            "connect-src 'self'{$devServer}{$socket}{$google('connect')}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    /**
     * The origin of the Vite dev server while it runs (the `vite` service in Sail). Only in local: a stray public/hot
     * must never widen the policy in production, and tests must not depend on a running dev server.
     */
    private function devServer(): string
    {
        if (! App::isLocal() || ! Vite::isRunningHot()) {
            return '';
        }

        return trim(File::get(Vite::hotFile()));
    }
}
