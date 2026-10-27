<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    /**
     * Sonner injects its stylesheet at import time with no nonce hook: an empty <style> first, then its CSS.
     * These hashes allow exactly those two contents. Upgrading sonner changes the second one: the browser
     * tests then report the blocked style and the hash is updated here.
     */
    private const string SONNER_STYLES = "'sha256-47DEQpj8HBSa+/TImW+5JCeuQeRkm5NMpJWZG3hSuFU=' 'sha256-StEaX+se6YS7pqjzrzMIA0KaX9zF/8zAhvQXZAe5epY='";

    /**
     * Send a nonce-based Content-Security-Policy and the standard security headers.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $devServer = $this->devServer();

        $response = $next($request);

        $response->headers->add([
            'Content-Security-Policy' => $this->policy($nonce, $devServer),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ]);

        if (App::isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function policy(string $nonce, string $devServer): string
    {
        $socket = $devServer === '' ? '' : ' '.str_replace('http', 'ws', $devServer);
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
     * The origin of the Vite dev server, only while `composer dev` runs it (never in testing or production builds).
     */
    private function devServer(): string
    {
        if (! Vite::isRunningHot()) {
            return '';
        }

        return trim(File::get(Vite::hotFile()));
    }
}
