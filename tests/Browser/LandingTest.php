<?php

declare(strict_types=1);

use App\Modules\Demos\Models\DemoRequest;

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

/**
 * Fills the demo form with a valid request, the way a visitor would.
 *
 * @param  Pest\Browser\Api\AwaitableWebpage|Pest\Browser\Api\Webpage  $page
 */
function fillDemoForm(mixed $page): void
{
    $page->fill('name', 'Ana López')
        ->fill('email', 'ana@empresa.mx')
        ->fill('whatsapp', '55 1234 5678')
        ->fill('company', 'Empresa Ejemplo')
        ->radio('profile', 'consultant')
        ->radio('workforce', '51_100')
        ->fill('message', 'Aplicamos la Guía III a tres clientes.');
}

it('stores a demo request and confirms it', function (bool $mobile): void {
    $page = visitPage('landing', '/', mobile: $mobile, reach: function ($page): void {
        fillDemoForm($page);
        $page->press('@demo-request-submit')
            ->waitForText('Recibimos tu solicitud · te escribiremos para agendar la demo.');
    });

    $page->assertScript('() => window.location.hash', '#agenda-una-demo')
        ->assertValue('name', '')
        ->assertValue('message', '')
        ->assertRadioNotSelected('profile');

    $demoRequest = DemoRequest::query()->sole();

    expect($demoRequest->whatsapp)->toBe('+525512345678')
        ->and($demoRequest->profile->value)->toBe('consultant')
        ->and($demoRequest->workforce->value)->toBe('51_100');
})->with(['desktop' => false, 'mobile' => true]);

it('shows what to fix next to each field when the form is sent empty', function (bool $mobile): void {
    $page = visitPage('landing', '/', mobile: $mobile, reach: function ($page): void {
        $page->press('@demo-request-submit')->waitForText('El campo nombre es obligatorio.');
    });

    foreach ([
        'name' => 'El campo nombre es obligatorio.',
        'email' => 'El campo correo electrónico es obligatorio.',
        'whatsapp' => 'El campo WhatsApp es obligatorio.',
        'company' => 'El campo empresa es obligatorio.',
    ] as $field => $error) {
        $page->assertAttribute("[name=\"{$field}\"]", 'aria-invalid', 'true')
            ->assertScript("() => document.getElementById(document.querySelector('[name=\"{$field}\"]').getAttribute('aria-describedby')).textContent", $error);
    }

    foreach ([
        'profile' => 'El campo perfil es obligatorio.',
        'workforce' => 'El campo número de trabajadores es obligatorio.',
    ] as $group => $error) {
        $page->assertAttribute("[data-test=\"{$group}-group\"]", 'aria-invalid', 'true')
            ->assertScript("() => document.getElementById(document.querySelector('[data-test=\"{$group}-group\"]').getAttribute('aria-describedby')).textContent", $error);
    }

    $page->assertAttributeMissing('[name="message"]', 'aria-invalid');

    expect(DemoRequest::query()->count())->toBe(0);
})->with(['desktop' => false, 'mobile' => true]);

it('tells the visitor to wait when there were too many requests', function (): void {
    $page = visitPage('landing', '/');

    foreach (range(1, 5) as $attempt) {
        fillDemoForm($page);
        $page->press('@demo-request-submit')->assertValue('name', '');
    }

    fillDemoForm($page);
    $page->press('@demo-request-submit')
        ->waitForText('Recibimos demasiadas solicitudes desde tu conexión. Espera un momento e inténtalo de nuevo.')
        ->assertNoJavaScriptErrors()
        ->assertMissing('dialog')
        ->assertValue('name', 'Ana López');

    expect(DemoRequest::query()->count())->toBe(5);
});
