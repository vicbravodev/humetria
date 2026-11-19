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

    /** Whether the platform has an identity provider for its own staff. */
    public function staffConfigured(): bool
    {
        return config()->string('sso.staff.issuer', '') !== '' && config()->string('sso.staff.client_id', '') !== ''
            && config()->string('sso.staff.client_secret', '') !== '' && config()->string('sso.staff.domain', '') !== '';
    }

    /**
     * @throws SsoSignInFailed
     */
    public function forStaff(Request $request, string $nonce): OidcProvider
    {
        return new OidcProvider(
            $request,
            config()->string('sso.staff.client_id'),
            config()->string('sso.staff.client_secret'),
            route('sso.staff.callback'),
            $this->discovery->endpoints(config()->string('sso.staff.issuer')),
            $nonce,
        );
    }
}
