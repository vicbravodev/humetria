<?php

declare(strict_types=1);

namespace App\Modules\Sso\Support;

use App\Modules\Sso\Exceptions\SsoSignInFailed;

/**
 * The claims of an ID token received straight from the token endpoint, over TLS, in exchange for our own code and secret. OpenID
 * Connect Core (§3.1.3.7) lets a client trust that channel instead of the signature, so none is checked: the checks that matter
 * here are who issued it, who it is for, that it is fresh and that it answers the nonce of this very sign-in.
 * ponytail: no JWKS signature check; add one if a flow other than the code flow is ever used.
 */
final class IdToken
{
    private const int LEEWAY_SECONDS = 60;

    /**
     * @return array<string, mixed>
     *
     * @throws SsoSignInFailed
     */
    public static function claims(string $jwt, string $issuer, string $clientId, string $nonce): array
    {
        $parts = explode('.', $jwt);
        $payload = count($parts) === 3 ? json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true) : null;

        if (! is_array($payload)) {
            throw new SsoSignInFailed('malformed_token');
        }

        $claims = [];

        foreach ($payload as $name => $value) {
            $claims[(string) $name] = $value;
        }

        $audience = $claims['aud'] ?? null;
        $audiences = is_array($audience) ? $audience : [$audience];

        return match (true) {
            ! is_string($claims['iss'] ?? null) || rtrim($claims['iss'], '/') !== $issuer => throw new SsoSignInFailed('wrong_issuer'),
            ! in_array($clientId, $audiences, true) || (count($audiences) > 1 && ($claims['azp'] ?? null) !== $clientId) => throw new SsoSignInFailed('wrong_audience'),
            ! is_int($claims['exp'] ?? null) || time() > $claims['exp'] + self::LEEWAY_SECONDS => throw new SsoSignInFailed('expired_token'),
            ! is_string($claims['nonce'] ?? null) || ! hash_equals($nonce, $claims['nonce']) => throw new SsoSignInFailed('wrong_nonce'),
            default => $claims,
        };
    }
}
