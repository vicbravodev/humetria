<?php

declare(strict_types=1);

/*
 * The landing at `/`: its health in every look lives in PagesTest; here, that its calls to action lead where they say.
 */

it('takes the header and hero calls to action to the demo section', function (string $button, bool $mobile): void {
    visitPage('landing', '/', mobile: $mobile)
        ->click($button)
        ->assertScript('() => window.location.hash', '#agenda-una-demo')
        ->assertScript('() => new Promise((resolve) => setTimeout(() => { const box = document.getElementById("agenda-una-demo").getBoundingClientRect(); resolve(box.top < window.innerHeight && box.bottom > 0); }, 1000))');
})->with([
    'header' => '@header-demo',
    'hero' => '@hero-demo',
])->with(['desktop' => false, 'mobile' => true]);

it('sends a guest who signs in to the login page', function (bool $mobile): void {
    visitPage('landing', '/', mobile: $mobile)
        ->click('@sign-in')
        ->assertPathIs('/admin/login');
})->with(['desktop' => false, 'mobile' => true]);

it('shows the demo section without a fake form', function (): void {
    visitPage('landing', '/')
        ->assertPresent('#agenda-una-demo h2')
        ->assertMissing('#agenda-una-demo form')
        ->assertDontSee('Próximamente');
});
