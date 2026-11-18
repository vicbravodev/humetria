<?php

declare(strict_types=1);

namespace App\Modules\Sso\Support;

use App\Modules\Sso\Exceptions\SsoSignInFailed;
use App\Modules\Sso\Models\SsoConnection;
use Illuminate\Http\Request;

/** Builds the code flow of a company's own identity provider: its discovery, its credentials and the callback of the platform. */
final readonly class OidcProviders
{
    public function __construct(private OidcDiscovery $discovery) {}

    /**
     * @throws SsoSignInFailed
     */
    public function for(SsoConnection $connection, Request $request, string $nonce): OidcProvider
    {
        return new OidcProvider(
            $request,
            $connection->client_id,
            $connection->client_secret,
            route('sso.callback'),
            $this->discovery->endpoints($connection->issuer),
            $nonce,
        );
    }
}
