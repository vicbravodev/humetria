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
        ->check('tools[]', 'nom_035')
        ->check('tools[]', 'nom_037')
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
        ->assertRadioNotSelected('profile')
        ->assertScript('() => [...document.querySelectorAll(\'[name="tools[]"]\')].every((box) => !box.checked)');

    $demoRequest = DemoRequest::query()->sole();

    expect($demoRequest->whatsapp)->toBe('+525512345678')
        ->and($demoRequest->profile->value)->toBe('consultant')
        ->and($demoRequest->workforce->value)->toBe('51_100')
        ->and($demoRequest->tools->map->value->all())->toBe(['nom_035', 'nom_037']);
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
        'tools' => 'El campo herramientas es obligatorio.',
        'workforce' => 'El campo número de trabajadores es obligatorio.',
    ] as $group => $error) {
        $page->assertAttribute("[data-test=\"{$group}-group\"]", 'aria-invalid', 'true')
            ->assertScript("() => document.getElementById(document.querySelector('[data-test=\"{$group}-group\"]').getAttribute('aria-describedby')).textContent", $error);
    }

    $page->assertAttributeMissing('[name="message"]', 'aria-invalid')
        ->assertScript('() => document.activeElement.name', 'name')
        ->assertScript('() => [...document.querySelectorAll("#agenda-una-demo [id$=\'-error\']")].every((error) => error.getAttribute("role") === "alert")');

    expect(DemoRequest::query()->count())->toBe(0);
})->with(['desktop' => false, 'mobile' => true]);

it('asks who Humetría is for', function (): void {
    visit('/')
        ->assertSee('¿Para quién es Humetría?')
        ->assertSee('Para mi empresa o grupo de empresas')
        ->assertSee('Soy consultor y atiendo a varias empresas');
});

it('hides the honeypot from people, keyboards and autofill', function (): void {
    visitPage('landing', '/')
        ->assertMissing('[name="website"]')
        ->assertAttribute('[name="form_extra"]', 'autocomplete', 'off')
        ->assertAttribute('[name="form_extra"]', 'tabindex', '-1')
        ->assertScript('() => document.querySelector("[name=form_extra]").closest("[aria-hidden=true]") !== null')
        ->assertScript('() => document.querySelector("[name=form_extra]").getBoundingClientRect().right < 0');
});

it('moves focus to the first field left to fix', function (): void {
    $page = visitPage('landing', '/');

    fillDemoForm($page);
    $page->fill('email', 'ana@')
        ->press('@demo-request-submit')
        ->waitForText('El campo correo electrónico debe ser un correo electrónico válido.')
        ->assertScript('() => document.activeElement.name', 'email');
});

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

it('presents Humetría as an HR platform with its three tools', function (): void {
    visitPage('landing', '/')
        ->assertTitleContains('Herramientas de RH')
        ->assertScript('() => ["NOM-035", "NOM-037", "Ley Silla"].every((tool) => document.querySelector("meta[name=description]").content.includes(tool))')
        ->assertScript('() => document.querySelector("[data-test=hero-eyebrow]").textContent', 'NOM-035 · NOM-037 · Ley Silla')
        ->assertDontSeeIn('#hero-title', 'NOM-035');
});

it('previews a work center with the state of the three tools', function (): void {
    visitPage('landing', '/')
        ->assertSeeIn('@hero-preview', 'NOM-035')
        ->assertSeeIn('@hero-preview', 'NOM-037')
        ->assertSeeIn('@hero-preview', 'Ley Silla')
        ->assertSeeIn('figure [data-test="preview-nom_035"] [data-slot="badge"]', 'En curso');
});

it('takes the header tools link to the tools section', function (): void {
    // The link shows from the sm breakpoint up: on a phone the header only fits the logo and the two calls to action.
    visitPage('landing', '/')
        ->click('@header-tools')
        ->assertScript('() => window.location.hash', '#herramientas')
        ->assertScript('() => new Promise((resolve) => setTimeout(() => { const box = document.getElementById("herramientas").getBoundingClientRect(); resolve(box.top < window.innerHeight && box.bottom > 0); }, 1000))');
});

it('details each of the three tools', function (): void {
    $page = visitPage('landing', '/')
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test=tool-card]").length', 3);

    foreach ([
        'NOM-035-STPS-2018 · Factores de riesgo psicosocial',
        'NOM-037-STPS-2023 · Teletrabajo',
        'Ley Federal del Trabajo, arts. 132 y 133 · Asientos y descansos',
    ] as $index => $official) {
        $page->assertScript("() => document.querySelectorAll('#herramientas [data-test=tool-card]')[{$index}].querySelector('[data-test=tool-official]').textContent", $official);
    }

    $page->assertScript('() => document.querySelectorAll("#herramientas [data-test=tool-card]")[1].textContent.toLowerCase().includes("lista de verificación")')
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test=tool-card]")[2].textContent.includes("reglamento interior")')
        ->assertScript('() => document.getElementById("herramientas").textContent.toLowerCase().includes("próximamente")', false);
});
