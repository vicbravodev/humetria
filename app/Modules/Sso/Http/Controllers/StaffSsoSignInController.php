<?php

declare(strict_types=1);

namespace App\Modules\Sso\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sso\Actions\CompleteStaffSsoSignIn;
use App\Modules\Sso\Exceptions\SsoSignInFailed;
use App\Modules\Sso\Support\OidcProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Two\InvalidStateException;

/** Sign-in of Humetría's own staff with the platform's identity provider; every refusal is the same page. */
final class StaffSsoSignInController extends Controller
{
    private const string GENERIC = 'No pudimos iniciar tu sesión con SSO. Si el problema sigue, entra con tu contraseña o llave de acceso.';

    public function start(Request $request, OidcProviders $providers): RedirectResponse
    {
        abort_unless($providers->staffConfigured(), 404);

        $nonce = Str::random(40);

        try {
            $redirect = $providers->forStaff($request, $nonce)->redirect();
        } catch (SsoSignInFailed $failed) {
            return $this->refused($failed->reason);
        }

        $request->session()->put('sso.staff_nonce', $nonce);

        return $redirect;
    }

    public function callback(Request $request, OidcProviders $providers, CompleteStaffSsoSignIn $complete): RedirectResponse
    {
        $nonce = $request->session()->pull('sso.staff_nonce');

        if (! is_string($nonce) || ! $providers->staffConfigured()) {
            return $this->refused('no_sign_in_started');
        }

        try {
            $user = $complete->handle($providers->forStaff($request, $nonce)->identity());
        } catch (SsoSignInFailed $failed) {
            return $this->refused($failed->reason);
        } catch (InvalidStateException) {
            return $this->refused('invalid_state');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    private function refused(string $reason): RedirectResponse
    {
        Log::info('sso.staff_sign_in_failed', ['reason' => $reason]);

        return to_route('login')->withErrors(['sso' => self::GENERIC]);
    }
}
