<?php

declare(strict_types=1);

namespace App\Modules\Sso\Contracts;

/**
 * Whether the DNS of a domain proves its owner asked for single sign-on: the TXT record at `_humetria-sso.{domain}` carries
 * `humetria-sso-verification={token}`. Behind a contract so tests answer it without a network.
 */
interface DomainOwnership
{
    public function proves(string $domain, string $token): bool;
}
