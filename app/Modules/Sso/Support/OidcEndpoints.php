<?php

declare(strict_types=1);

namespace App\Modules\Sso\Support;

/** What the discovery document of an identity provider says we need. */
final readonly class OidcEndpoints
{
    public function __construct(public string $issuer, public string $authorization, public string $token) {}
}
