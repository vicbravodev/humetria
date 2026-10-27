<?php

declare(strict_types=1);

it('renders public pages without javascript errors on desktop and mobile', function (string $path): void {
    visit($path)->assertNoJavaScriptErrors()->assertNoConsoleLogs()->assertScript(cspViolations(), '[]');

    visit($path)->on()->mobile()->assertNoJavaScriptErrors()->assertNoConsoleLogs()->assertScript(cspViolations(), '[]');
})->with(['/', '/admin/login', '/admin/forgot-password']);

it('offers no sign-up link on the login page or the home page', function (string $path): void {
    visit($path)->assertDontSee('Regístrate')->assertDontSee('Register');
})->with(['/admin/login', '/']);
