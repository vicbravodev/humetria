<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Playwright;

// WebAuthn refuses a relying party on 127.0.0.1, so the app is opened on localhost (same server), as the time clock tests do.
beforeEach(function (): void {
    Playwright::setHost('localhost');
});

/*
 * The app lives under /admin, but @laravel/passkeys asks for /user/passkeys and /passkeys/login unless it is told where.
 * Production answered 404 to "Agregar llave" for that reason. These drive a real browser with a software authenticator and
 * read the requests the page makes, so a URL without the prefix fails here the way it failed for a person.
 */

/** Records the path and status of every fetch the page makes, as `[path, status]` pairs in `window.__calls`. */
function fetchSpy(): string
{
    return <<<'JS'
        () => {
            window.__calls = [];
            const original = window.fetch;
            window.fetch = async (...args) => {
                const response = await original(...args);
                window.__calls.push([new URL(String(args[0]), location.origin).pathname, response.status]);
                return response;
            };
            return true;
        }
        JS;
}

/** Resolves with the first request the spy saw, as `path,status` (or `none` after five seconds). */
function firstSpiedCall(): string
{
    return <<<'JS'
        () => new Promise((resolve) => {
            const start = performance.now();
            const check = () => {
                window.__calls.length > 0 || performance.now() - start > 5000
                    ? resolve(window.__calls[0]?.join(',') ?? 'none')
                    : setTimeout(check, 50);
            };
            check();
        })
        JS;
}

it('registers a passkey from the security settings through the /admin routes', function (): void {
    test()->actingAs(User::factory()->create());

    $page = visitPage('settings/security', '/admin/settings/security', reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->assertSee('Confirma tu contraseña para continuar')
        ->fill('password', 'password')
        ->press('@confirm-password-button')
        ->assertScript(pathBecomes('/admin/settings/security'), true));

    withFingerprintReader($page);
    // The helper bends Fortify's settings; laravel/passkeys copied them at boot and verifies against its own.
    config([
        'passkeys.relying_party_id' => 'localhost',
        'passkeys.allowed_origins' => config('fortify.passkeys.allowed_origins'),
    ]);
    $page->script(fetchSpy());

    $page->press('Agregar llave')
        ->fill('#passkey-name', 'Llave de la oficina')
        ->press('Registrar llave de acceso')
        ->waitForText('Llave de la oficina')
        ->assertNoJavaScriptErrors()
        ->assertScript(firstSpiedCall(), '/admin/user/passkeys/options,200');

    expect(DB::table('passkeys')->where('name', 'Llave de la oficina')->exists())->toBeTrue();
});

it('asks for the passkey sign-in options through the /admin routes', function (): void {
    $page = visitPage('auth/login', '/admin/login');

    withFingerprintReader($page);
    $page->script(fetchSpy());

    $page->press('Entrar con llave de acceso')
        ->assertScript(firstSpiedCall(), '/admin/passkeys/login/options,200');
});
