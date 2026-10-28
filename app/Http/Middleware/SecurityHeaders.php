<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a nonce-based Content-Security-Policy and the standard security headers on every response.
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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $devServer = $this->devServer();

        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->policy($nonce, $devServer),
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

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
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

    private function policy(string $nonce, string $devServer): string
    {
        $socket = $devServer === '' ? '' : ' '.preg_replace('/^http/', 'ws', $devServer);
        $devServer = $devServer === '' ? '' : ' '.$devServer;

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'{$devServer}",
            "style-src 'self' 'nonce-{$nonce}' ".self::SONNER_STYLES.$devServer,
            "img-src 'self' data:{$devServer}",
            "font-src 'self'{$devServer}",
            "connect-src 'self'{$devServer}{$socket}",
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
