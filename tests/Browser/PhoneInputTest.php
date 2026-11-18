<?php

declare(strict_types=1);

use App\Modules\Demos\Models\DemoRequest;

/*
 * The phone field (PhoneInput), on the landing's demo form, where anyone reaches it: Mexico first, the mask while
 * typing, a number pasted from another country, and the E.164 it sends.
 */

/** The demo form filled but for the WhatsApp. */
function demoFormButThePhone(mixed $page): mixed
{
    return $page->fill('name', 'Ana López')
        ->fill('email', 'ana@empresa.mx')
        ->fill('company', 'Empresa Ejemplo');
}

/** What the phone sends: its hidden field. */
const SENT_WHATSAPP = '() => document.querySelector(\'input[type="hidden"][name="whatsapp"]\').value';

it('starts on Mexico, with its flag and code, and masks the number while it is typed', function (bool $dark, bool $mobile): void {
    $page = visitPage('landing', '/', $dark, $mobile)
        ->assertValue('select[aria-label="País del teléfono"]', 'MX')
        ->assertSeeIn('#agenda-una-demo [data-test="phone-prefix"]', '+52')
        // The flag is an SVG file loaded for its country: drawn alike on every system, unlike an emoji.
        ->assertScript('() => new Promise((resolve) => { const wait = () => { const flag = document.querySelector("#agenda-una-demo [data-slot=input-group] img"); flag?.complete && flag.naturalWidth > 0 ? resolve(true) : setTimeout(wait, 50); }; wait(); })', true)
        ->type('#demo-whatsapp', '5512345678')
        ->assertValue('#demo-whatsapp', '55 1234 5678')
        ->assertScript(SENT_WHATSAPP, '+525512345678')
        ->assertNoJavaScriptErrors()
        ->assertScript(cspViolations(), '[]');

    if ($mobile) {
        // The field fits a 375 px phone: nothing of it sticks out of its box.
        $page->assertScript('() => { const box = document.querySelector("#agenda-una-demo [data-slot=input-group]").getBoundingClientRect(); return box.right <= window.innerWidth && document.documentElement.scrollWidth <= window.innerWidth; }', true);
    }
})->with([
    'light, desktop' => [false, false],
    'dark, desktop' => [true, false],
    'light, 375 px' => [false, true],
    'dark, 375 px' => [true, true],
]);

it('reads a number pasted with another country code, and moves the field to that country', function (): void {
    visit('/')
        ->fill('#demo-whatsapp', '+1 415 555 2671')
        ->assertValue('select[aria-label="País del teléfono"]', 'US')
        ->assertSeeIn('#agenda-una-demo [data-test="phone-prefix"]', '+1')
        ->assertValue('#demo-whatsapp', '(415) 555-2671')
        ->assertScript(SENT_WHATSAPP, '+14155552671')
        ->assertNoJavaScriptErrors();
});

it('masks the digits as written in the country chosen, and sends a Mexican number pasted the old way in E.164', function (): void {
    visit('/')
        ->fill('#demo-whatsapp', '044 55 1234 5678')
        ->assertValue('#demo-whatsapp', '55 1234 5678')
        ->assertScript(SENT_WHATSAPP, '+525512345678')
        ->select('select[aria-label="País del teléfono"]', 'ES')
        ->fill('#demo-whatsapp', '612345678')
        ->assertValue('#demo-whatsapp', '612 34 56 78')
        ->assertScript(SENT_WHATSAPP, '+34612345678')
        ->assertNoJavaScriptErrors();
});

it('stores the WhatsApp of a demo request in E.164 when it comes from another country', function (): void {
    demoFormButThePhone(visit('/'))
        ->fill('#demo-whatsapp', '+1 415 555 2671')
        ->press('@demo-request-submit')
        ->waitForText('Recibimos tu solicitud · te escribiremos para agendar la demo.')
        // The form starts over, the phone too: empty and back on Mexico.
        ->assertValue('#demo-whatsapp', '')
        ->assertValue('select[aria-label="País del teléfono"]', 'MX')
        ->assertNoJavaScriptErrors();

    expect(DemoRequest::query()->sole()->whatsapp)->toBe('+14155552671');
});

it('puts the focus on the phone box when only the phone is wrong', function (): void {
    demoFormButThePhone(visit('/'))
        ->fill('#demo-whatsapp', '55 1234')
        ->press('@demo-request-submit')
        ->waitForText('Revisa el WhatsApp: no es un número válido para el país elegido.')
        ->assertAttribute('#demo-whatsapp', 'aria-invalid', 'true')
        ->assertScript('() => document.activeElement.id', 'demo-whatsapp');

    expect(DemoRequest::query()->count())->toBe(0);
});
