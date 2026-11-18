<?php

declare(strict_types=1);

namespace App\Modules\Sso\Support;

use App\Modules\Sso\Exceptions\SsoSignInFailed;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use LogicException;

/**
 * The code flow of OpenID Connect against the provider of one company, with state, PKCE and a nonce. Socialite keeps the state
 * and the PKCE verifier in the session; the nonce is ours.
 */
final class OidcProvider extends AbstractProvider
{
    /** @var list<string> */
    protected $scopes = ['openid', 'email'];

    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    public function __construct(
        Request $request,
        string $clientId,
        string $clientSecret,
        string $redirectUrl,
        private readonly OidcEndpoints $endpoints,
        private readonly string $nonce,
    ) {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl);
    }

    /**
     * Who the ID token that answers this callback says is signing in.
     *
     * @throws InvalidStateException
     * @throws SsoSignInFailed
     */
    public function identity(): IdentityClaims
    {
        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }

        $idToken = $this->getAccessTokenResponse($this->getCode())['id_token'] ?? null;

        if (! is_string($idToken)) {
            throw new SsoSignInFailed('no_id_token');
        }

        return IdentityClaims::from(IdToken::claims($idToken, $this->endpoints->issuer, $this->clientId, $this->nonce));
    }

    /**
     * Through Laravel's HTTP client, so it times out and tests can fake it.
     *
     * @param  string  $code
     * @return array<string, mixed>
     *
     * @throws SsoSignInFailed
     */
    public function getAccessTokenResponse($code): array
    {
        try {
            $body = Http::timeout(10)->asForm()->acceptJson()->post($this->getTokenUrl(), $this->named($this->getTokenFields($code)))->throw()->json();
        } catch (ConnectionException|RequestException) {
            throw new SsoSignInFailed('token_exchange_failed');
        }

        return $this->named(is_array($body) ? $body : []);
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->endpoints->authorization, $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->endpoints->token;
    }

    /**
     * @param  string|null  $state
     * @return array<string, mixed>
     */
    protected function getCodeFields($state = null): array
    {
        return [...$this->named(parent::getCodeFields($state)), 'nonce' => $this->nonce];
    }

    protected function getUserByToken($token): never
    {
        throw new LogicException('The person comes from the ID token, never from the access token.');
    }

    /**
     * @param  array<string, mixed>  $user
     */
    protected function mapUserToObject(array $user): never
    {
        throw new LogicException('The person comes from the ID token, never from a user object.');
    }

    /**
     * @param  array<array-key, mixed>  $fields
     * @return array<string, mixed>
     */
    private function named(array $fields): array
    {
        $named = [];

        foreach ($fields as $name => $value) {
            $named[(string) $name] = $value;
        }

        return $named;
    }
}
