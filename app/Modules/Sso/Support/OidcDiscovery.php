<?php

declare(strict_types=1);

namespace App\Modules\Sso\Support;

use App\Modules\Sso\Exceptions\SsoSignInFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Reads the discovery document of the issuer a company configured. The issuer it names must be the one asked for, and every
 * endpoint must be https: a document that says otherwise is not trusted.
 */
final class OidcDiscovery
{
    /**
     * @throws SsoSignInFailed
     */
    public function endpoints(string $issuer): OidcEndpoints
    {
        try {
            $document = Http::timeout(5)->acceptJson()->get($issuer.'/.well-known/openid-configuration')->throw()->json();
        } catch (ConnectionException|RequestException) {
            throw new SsoSignInFailed('discovery_unreachable');
        }

        $named = is_array($document) ? $document : [];

        if (! is_string($named['issuer'] ?? null) || rtrim($named['issuer'], '/') !== $issuer) {
            throw new SsoSignInFailed('discovery_issuer_mismatch');
        }

        $authorization = $named['authorization_endpoint'] ?? null;
        $token = $named['token_endpoint'] ?? null;

        if (! is_string($authorization) || ! is_string($token) || ! Str::startsWith($authorization, 'https://') || ! Str::startsWith($token, 'https://')) {
            throw new SsoSignInFailed('discovery_endpoints_invalid');
        }

        return new OidcEndpoints($issuer, $authorization, $token);
    }
}
