<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;

// Rediseño v2 (C4): a zone to drop or choose a file instead of the native «Choose File / No file chosen», with the chosen
// file named in it. The real input stays inside, so the form sends the file as before.
it('takes a file in a drop zone and names it, in both themes and on a phone', function (bool $dark, bool $mobile): void {
    $url = signInToPanel();
    WorkCenter::factory()->for(Tenant::query()->where('slug', 'acme')->firstOrFail())->create(['name' => 'Planta Querétaro']);

    visitPage('telework/documents', "{$url}/telework/documents", $dark, $mobile)
        ->assertSee('Arrastra el PDF aquí o elígelo')
        ->assertScript('() => [...document.querySelectorAll("input[type=file]")].every((input) => input.getBoundingClientRect().width <= 1)', true)
        ->attach('document', __DIR__.'/../Fixtures/policy.pdf')
        ->assertSee('policy.pdf');
})->with(['light' => false, 'dark' => true])->with(['desktop' => false, 'phone' => true]);
