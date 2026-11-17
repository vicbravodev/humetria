<?php

declare(strict_types=1);

namespace App\Modules\Sso\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sso\Actions\CompleteSsoSignIn;
use App\Modules\Sso\Actions\FindSsoCompany;
use App\Modules\Sso\Actions\OpenSsoSignIn;
use App\Modules\Sso\Exceptions\SsoSignInFailed;
use App\Modules\Sso\Support\OidcProviders;
use App\Modules\Tenancy\Contracts\CompanyRegistry;
use Illuminate\Container\Attributes\RouteParameter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;

/**
 * Sign-in with a company's own identity provider, for people who already have an account in it. Guests reach it, so every
 * refusal is the same page with the same words: nothing says whether a company or a person exists.
 */
final class SsoSignInController extends Controller
{
    private const string GENERIC = 'No pudimos iniciar tu sesión con SSO. Si el problema sigue, pide ayuda a quien administra tu cuenta.';

    public function discover(Request $request, FindSsoCompany $find): RedirectResponse
    {
        $slug = $find->handle($request->string('email')->toString());

        return $slug === null ? $this->refused('unknown_domain', null) : to_route('sso.start', $slug);
    }

    public function start(Request $request, #[RouteParameter('tenant')] string $company, CompanyRegistry $companies, OpenSsoSignIn $open, OidcProviders $providers): RedirectResponse
    {
        $brief = $companies->findBySlug($company);
        $connection = $brief === null ? null : $open->handle($brief->id);
        abort_if($brief === null || $connection === null, 404);

        $nonce = Str::random(40);

        try {
            $redirect = $providers->for($connection, $request, $nonce)->redirect();
        } catch (SsoSignInFailed $failed) {
            return $this->refused($failed->reason, $brief->id);
        }

        $request->session()->put(['sso.tenant_id' => $brief->id, 'sso.nonce' => $nonce]);

        return $redirect;
    }

    public function callback(Request $request, OpenSsoSignIn $open, OidcProviders $providers, CompleteSsoSignIn $complete, CompanyRegistry $companies): RedirectResponse
    {
        $tenantId = $request->session()->pull('sso.tenant_id');
        $nonce = $request->session()->pull('sso.nonce');

        if (! is_string($tenantId) || ! is_string($nonce)) {
            return $this->refused('no_sign_in_started', null);
        }

        $connection = $open->handle($tenantId);

        if ($connection === null) {
            return $this->refused('not_available', $tenantId);
        }

        try {
            $user = $complete->handle($tenantId, $providers->for($connection, $request, $nonce)->identity());
        } catch (SsoSignInFailed $failed) {
            return $this->refused($failed->reason, $tenantId);
        } catch (InvalidStateException) {
            return $this->refused('invalid_state', $tenantId);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('sso.tenant_id', $tenantId);

        return redirect()->intended(route('tenant.dashboard', $companies->find($tenantId)->slug ?? ''));
    }

    private function refused(string $reason, ?string $tenantId): RedirectResponse
    {
        Log::info('sso.sign_in_failed', ['tenant_id' => $tenantId, 'reason' => $reason]);

        return to_route('login')->withErrors(['sso' => self::GENERIC]);
    }
}
