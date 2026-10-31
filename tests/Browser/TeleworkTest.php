<?php

declare(strict_types=1);

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows the NOM-037 settings with the default period and the base consent', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();

    visitPage('telework/settings', "{$url}/telework/settings", $dark, $mobile)
        ->assertSee('Ajustes de NOM-037')
        ->assertSee('Versión 1')
        ->assertScript('document.querySelector("input[type=radio]:checked")?.parentElement?.textContent', '12 meses');
})->with($looks);

it('saves a custom period and a new consent text', function (): void {
    $url = signInToPanel();

    visit("{$url}/telework/settings")
        ->click('Otro')
        ->type('#period-custom', '18')
        ->type('consent_text', 'Texto propio de la empresa.')
        ->press('Guardar ajustes')
        ->assertSee('Ajustes de NOM-037 guardados.')
        ->assertSee('Versión 2')
        ->assertValue('#period-custom', '18')
        ->assertNoJavaScriptErrors();
});
