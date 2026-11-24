<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Pest\Browser\Api\AwaitableWebpage;

/*
 * Every Inertia page, opened the way a user reaches it, in each look: see visitPage() in tests/Pest.php.
 * Login and dashboard live in DesignSystemTest, which also checks the logo.
 */

dataset('looks', [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

it('renders the landing page', function (bool $dark, bool $mobile): void {
    visitPage('landing', '/', $dark, $mobile);
})->with('looks');

it('renders the privacy notice', function (bool $dark, bool $mobile): void {
    visitPage('privacy-notice', '/privacy-notice', $dark, $mobile)
        ->assertSee('Aviso de privacidad')
        ->assertSee('Quién es el responsable')
        ->assertSee('Cómo ejercer tus derechos');
})->with('looks');

it('renders the public roadmap', function (bool $dark, bool $mobile): void {
    visitPage('roadmap', '/roadmap', $dark, $mobile)
        ->assertSee('Lo que viene en Humetría')
        ->assertSee('Ahora')
        ->assertSee('Pronto')
        ->assertSee('Después')
        ->assertSee('Explorando');
})->with('looks');

it('wears the header of the landing on the roadmap, with the way back to its sections and the demo', function (): void {
    visitPage('roadmap', '/roadmap')
        ->assertAttribute('[data-test="header-how"]', 'href', '/#como-funciona')
        ->click('@header-resources')
        ->assertAttribute('[data-test="header-blog"]', 'href', '/blog')
        ->assertAttribute('[data-test="header-roadmap"]', 'aria-current', 'page')
        ->assertAttribute('[data-test="header-demo"]', 'href', '/#agenda-una-demo');
});

it('opens the menu of the header on the roadmap from a phone, with Blog and Roadmap in it', function (): void {
    visitPage('roadmap', '/roadmap', mobile: true)
        ->click('@tools-menu-mobile')
        ->assertVisible('#tools-menu a[data-test="menu-blog"]')
        ->assertVisible('#tools-menu a[data-test="menu-roadmap"]')
        // Painted, not just in the page: without the motion provider the panel stays at opacity 0.
        ->waitForEvent('networkidle')
        ->assertScript("() => new Promise((done) => setTimeout(() => done(getComputedStyle(document.querySelector('#tools-menu')).opacity === '1'), 600))");
});

it('filters the public roadmap by area and brings everything back', function (bool $dark, bool $mobile): void {
    visitPage('roadmap', '/roadmap', $dark, $mobile, function ($page): void {
        $page->assertSee('Jornada de 40 horas')
            ->click('[data-test="area-evaluation"]')
            ->assertSee('OKRs y KPIs')
            ->assertDontSee('Jornada de 40 horas')
            ->click('[data-test="area-all"]')
            ->assertSee('Jornada de 40 horas');
    });
})->with('looks');

it('renders the forgot password page', function (bool $dark, bool $mobile): void {
    visitPage('auth/forgot-password', '/admin/forgot-password', $dark, $mobile)
        ->assertSee('¿Olvidaste tu contraseña?')
        ->assertDontSee('Revisa tu correo');
})->with('looks');

it('says where the reset link went, waits before sending it again and lets the person change the email', function (bool $dark, bool $mobile): void {
    User::factory()->create(['email' => 'rita@autopartes.mx']);

    visitPage('auth/forgot-password', '/admin/forgot-password', $dark, $mobile, reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->fill('email', 'rita@autopartes.mx')
        ->press('Enviar enlace')
        ->assertSee('Revisa tu correo'))
        ->assertSee('Si rita@autopartes.mx tiene cuenta, te llegará un enlace para crear una contraseña nueva.')
        // The broker sends one link a minute: the button counts down to it, disabled until then.
        ->assertScript('() => [...document.querySelectorAll("button")].some((button) => /^Enviar otra vez en 0:[0-5]\\d|^Enviar otra vez en 1:00/.test(button.textContent) && button.disabled)')
        ->press('Usar otro correo')
        ->assertSee('¿Olvidaste tu contraseña?')
        ->assertValue('email', 'rita@autopartes.mx');
})->with('looks');

it('renders the reset password page from a real reset token', function (bool $dark, bool $mobile): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    visitPage('auth/reset-password', "/admin/reset-password/{$token}?email=".urlencode($user->email), $dark, $mobile)
        ->assertValue('input[name="email"]', $user->email);
})->with('looks');

it('renders the verify email page for an unverified user', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->unverified()->create());

    visitPage('auth/verify-email', '/admin/email/verify', $dark, $mobile);
})->with('looks');

it('renders the confirm password page before the security settings', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('auth/confirm-password', '/admin/settings/security', $dark, $mobile);
})->with('looks');

it('renders the two-factor challenge after the password', function (bool $dark, bool $mobile): void {
    $user = User::factory()->withTwoFactor()->create();

    visitPage('auth/two-factor-challenge', '/admin/login', $dark, $mobile, reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->fill('email', $user->email)
        ->fill('password', 'password')
        ->press('@login-button')
        ->assertScript(pathBecomes('/admin/two-factor-challenge'), true)
        ->assertPathIs('/admin/two-factor-challenge'));
})->with('looks');

it('renders the profile settings', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('settings/profile', '/admin/settings/profile', $dark, $mobile);
})->with('looks');

it('renders the appearance settings', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('settings/appearance', '/admin/settings/appearance', $dark, $mobile);
})->with('looks');

// Each miniature keeps its own theme's colors, so "Claro" still looks light while the page is dark.
it('draws each theme miniature in its own colors whatever the theme in use', function (bool $dark): void {
    $this->actingAs(User::factory()->create());

    visitPage('settings/appearance', '/admin/settings/appearance', $dark)
        ->assertScript('() => [...document.querySelectorAll(\'[aria-labelledby="theme-title"] .bg-sidebar\')].map((miniature) => getComputedStyle(miniature).backgroundColor).join(" ")', 'rgb(242, 246, 245) rgb(11, 18, 17) rgb(242, 246, 245) rgb(11, 18, 17)');
})->with(['light' => false, 'dark' => true]);

it('renders the security settings once the password is confirmed', function (bool $dark, bool $mobile): void {
    $this->actingAs(User::factory()->create());

    visitPage('settings/security', '/admin/settings/security', $dark, $mobile, reach: fn (AwaitableWebpage $page): AwaitableWebpage => $page
        ->fill('password', 'password')
        ->press('@confirm-password-button')
        ->assertScript(pathBecomes('/admin/settings/security'), true)
        ->assertPathIs('/admin/settings/security')
        ->assertSee('Guardar contraseña'));
})->with('looks');
