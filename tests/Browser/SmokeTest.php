<?php

declare(strict_types=1);

it('renders public pages without javascript errors on desktop and mobile', function (string $path): void {
    visit($path)->assertNoJavaScriptErrors()->assertNoConsoleLogs();

    visit($path)->on()->mobile()->assertNoJavaScriptErrors()->assertNoConsoleLogs();
})->with(['/', '/login', '/register', '/forgot-password']);
