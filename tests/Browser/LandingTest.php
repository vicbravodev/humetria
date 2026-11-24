<?php

declare(strict_types=1);

use App\Modules\Demos\Models\DemoRequest;

/** The tools section groups the nine tools in three families, in this order. */
const LANDING_FAMILIES = [
    'compliance' => ['nom_035', 'nom_037', 'ley_silla', 'attendance'],
    'listening' => ['complaints', 'engagement'],
    'operations' => ['leave', 'org_chart', 'compensation'],
];

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
        ->assertScript(pathBecomes('/admin/login'), true)
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
        ->fill('#demo-whatsapp', '55 1234 5678')
        ->fill('company', 'Empresa Ejemplo')
        // Opens the folded case details once: the form keeps them open after a request, so a second fill must not close them.
        ->script('() => { const toggle = document.querySelector(\'[data-test="demo-case-toggle"]\'); if (! toggle.parentElement.open) toggle.click(); }');

    $page->radio('profile', 'consultant')
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

it('stores a demo request from only the four contact fields, leaving the case details empty', function (): void {
    $page = visitPage('landing', '/', reach: function ($page): void {
        $page->fill('name', 'Ana López')
            ->fill('email', 'ana@empresa.mx')
            ->fill('#demo-whatsapp', '55 1234 5678')
            ->fill('company', 'Empresa Ejemplo')
            ->press('@demo-request-submit')
            ->waitForText('Recibimos tu solicitud · te escribiremos para agendar la demo.');
    });

    $page->assertNoJavaScriptErrors();

    $demoRequest = DemoRequest::query()->sole();

    expect($demoRequest->profile)->toBeNull()
        ->and($demoRequest->workforce)->toBeNull()
        ->and($demoRequest->tools->all())->toBe([]);
});

it('reports nothing and remembers no lead while PostHog is off: no token, no consent', function (): void {
    $page = visitPage('landing', '/', reach: function ($page): void {
        fillDemoForm($page);
        $page->press('@demo-request-submit')
            ->waitForText('Recibimos tu solicitud · te escribiremos para agendar la demo.');
    });

    expect(DemoRequest::query()->count())->toBe(1);

    $page->assertScript('() => document.getElementById("analytics-config") === null')
        ->assertScript('() => window.sessionStorage.getItem("analytics.lead_id") === null');
});

it('reports nothing when a request is rejected: not by validation and not by the honeypot', function (): void {
    $page = visitPage('landing', '/', reach: function ($page): void {
        $page->press('@demo-request-submit')->waitForText('El campo nombre es obligatorio.');
    });

    $page->assertScript('() => window.sessionStorage.getItem("analytics.lead_id") === null');
});

it('leaves the landing for the login in a new document, so PostHog never lives on another page', function (): void {
    visitPage('landing', '/', reach: function ($page): void {
        // A page Inertia navigates keeps its window; a plain link loads a document and loses this mark.
        $page->script('() => { window.landingDocument = "landing"; }');
    })->click('@sign-in')
        ->assertScript(pathBecomes('/admin/login'), true)
        ->assertScript('() => window.landingDocument === undefined');
});

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
        $page->assertAttribute("#demo-{$field}", 'aria-invalid', 'true')
            ->assertScript("() => document.getElementById(document.getElementById('demo-{$field}').getAttribute('aria-describedby')).textContent", $error);
    }

    // The case details are optional: they stay folded and nothing in them is flagged.
    $page->assertScript('() => document.querySelector(\'[data-test="demo-case"]\').open', false)
        ->assertScript('() => document.querySelectorAll(\'#agenda-una-demo [data-test$="-group"][aria-invalid]\').length', 0)
        ->assertAttributeMissing('[name="message"]', 'aria-invalid')
        ->assertScript('() => document.activeElement.name', 'name')
        ->assertScript('() => [...document.querySelectorAll("#agenda-una-demo [id$=\'-error\']")].every((error) => error.getAttribute("role") === "alert")');

    expect(DemoRequest::query()->count())->toBe(0);
})->with(['desktop' => false, 'mobile' => true]);

it('asks who Humetría is for, unions included, once the visitor opens their case', function (): void {
    visit('/')
        ->click('@demo-case-toggle')
        ->assertSee('¿Para quién es Humetría?')
        ->assertSee('Para mi empresa o grupo de empresas')
        ->assertSee('Soy consultor y atiendo a varias empresas')
        ->assertSee('Represento a un sindicato');
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

it('presents Humetría as an HR platform with all its tools', function (): void {
    visitPage('landing', '/')
        ->assertTitleContains('NOM-035, NOM-037 y Ley Silla')
        ->assertSeeIn('#hero-title', 'Cumple la ley y cuida a tu gente desde un solo lugar.')
        ->assertScript('() => ["NOM-035", "NOM-037", "Ley Silla", "quejas", "Compensación", "jornada", "Vacaciones", "Organigrama", "Clima"].every((tool) => document.querySelector("meta[name=description]").content.includes(tool))')
        ->assertScript('() => [...document.querySelectorAll("[data-test=hero-families] a b")].map((family) => family.textContent).join(" · ")', 'Cumple la norma · Escucha a tu gente · Personas y sueldos')
        ->assertScript('() => ["NOM-035", "NOM-037", "Ley Silla", "Registro de jornada", "Buzón de quejas", "Clima y pulso", "Vacaciones y permisos", "Organigrama", "Compensación"].every((tool) => document.querySelector("[data-test=hero-families]").textContent.includes(tool))')
        ->assertDontSeeIn('#hero-title', 'NOM-035');
});

it('gives the hero film the larger half of the desktop fold, with a headline that fits in three lines', function (): void {
    visitPage('landing', '/')
        ->assertScript('() => parseFloat(getComputedStyle(document.querySelector("#hero-title")).fontSize) <= 64')
        ->assertScript('() => document.querySelector("#hero-title").getBoundingClientRect().height <= 3.5 * parseFloat(getComputedStyle(document.querySelector("#hero-title")).fontSize)')
        ->assertScript('() => document.querySelector("[data-test=hero-video]").getBoundingClientRect().width >= 0.52 * document.querySelector("#inicio > div:nth-of-type(2)").clientWidth');
});

it('tells next to the hero button what happens after asking for a demo', function (bool $mobile): void {
    visitPage('landing', '/', mobile: $mobile)
        ->assertSeeIn('[data-test="hero-demo-note"]', 'Te escribimos por WhatsApp o por correo para agendar la llamada.')
        ->assertScript('() => document.querySelector("[data-test=hero-demo-note]").closest("[data-test=hero-actions]") !== null');
})->with(['desktop' => false, 'mobile' => true]);

it('plays the hero film muted and in a loop, and lets the visitor pause it', function (): void {
    $video = 'document.querySelector("[data-test=hero-video] video")';

    visitPage('landing', '/')
        ->assertScript("() => {$video}.muted && {$video}.loop && {$video}.playsInline")
        // The attribute, not only the property: axe's no-autoplay-audio rule reads it and otherwise waits for the narrated film to play.
        ->assertScript("() => {$video}.hasAttribute('muted')")
        ->assertScript("() => {$video}.poster.endsWith('/video/humetria-hero-light.jpg')")
        ->assertScript("() => [...{$video}.querySelectorAll('source')].map((source) => source.type).join()", 'video/webm,video/mp4')
        ->assertAttribute('@hero-video-toggle', 'aria-label', 'Pausar video')
        ->assertAttribute('@hero-video-sound', 'aria-label', 'Activar sonido')
        ->click('@hero-video-sound')
        ->assertScript("() => ! {$video}.muted")
        ->click('@hero-video-toggle')
        ->assertScript("() => {$video}.paused")
        ->assertAttribute('@hero-video-toggle', 'aria-label', 'Reproducir video');
});

it('plays the hero and Humi films again from the start', function (string $film): void {
    $video = "document.querySelector('[data-test={$film}] video')";

    $page = visitPage('landing', '/');
    // Halfway through and paused, the way a visitor leaves it.
    $page->script("() => new Promise((resolve) => { const video = {$video}; video.scrollIntoView(); video.pause(); video.addEventListener('seeked', () => resolve(true), { once: true }); video.currentTime = 12; })");

    $page->assertAttribute("@{$film}-restart", 'aria-label', 'Ver desde el inicio')
        ->click("@{$film}-restart")
        ->assertScript("() => {$video}.currentTime < 2")
        ->assertScript("() => new Promise((resolve) => { const playing = () => ! {$video}.paused ? resolve(true) : requestAnimationFrame(playing); playing(); })")
        ->assertAttribute("@{$film}-toggle", 'aria-label', 'Pausar video');
})->with(['hero' => 'hero-video', 'Humi' => 'humi-video']);

it('mutes a film when it scrolls out of view', function (string $film): void {
    $video = "document.querySelector('[data-test={$film}] video')";

    $page = visitPage('landing', '/');
    $page->script("() => {$video}.scrollIntoView({ block: 'center' })");

    $page->click("@{$film}-sound")
        ->assertScript("() => ! {$video}.muted")
        // The visitor scrolls on to another part of the page: the narration must not follow.
        ->script('() => window.scrollTo(0, document.body.scrollHeight)');

    $page->assertScript("() => new Promise((resolve) => { const quiet = () => {$video}.muted ? resolve(true) : requestAnimationFrame(quiet); quiet(); })")
        ->assertAttribute("@{$film}-sound", 'aria-label', 'Activar sonido');
})->with(['hero' => 'hero-video', 'Humi' => 'humi-video']);

it('keeps the hero film still when the visitor asks for reduced motion', function (): void {
    visit('/', ['reducedMotion' => 'reduce'])
        ->assertScript('() => document.querySelector("[data-test=hero-video] video").autoplay', false)
        ->assertScript('() => document.querySelector("[data-test=hero-video] video").paused')
        ->assertAttribute('@hero-video-toggle', 'aria-label', 'Reproducir video')
        ->assertNoJavaScriptErrors();
});

it('opens the tools from the header pill and takes «Ver las nueve herramientas» to the tools section', function (): void {
    // The link shows from the md breakpoint up: on a phone the header only fits the logo and the two calls to action.
    // The page glides until the section lands right under the 64 px sticky header (`scroll-mt-16`).
    $sectionTop = '() => Math.abs(document.getElementById("herramientas").getBoundingClientRect().top - 64) < 2';
    $landed = '() => new Promise((resolve) => { const settled = () => Math.abs(document.getElementById("herramientas").getBoundingClientRect().top - 64) < 2 ? resolve(true) : requestAnimationFrame(settled); settled(); })';

    visitPage('landing', '/')
        ->assertScript($sectionTop, false)
        ->click('@tools-menu')
        ->assertAttribute('@tools-menu', 'aria-expanded', 'true')
        ->assertVisible('#tools-menu')
        ->click('@tools-menu-all')
        ->assertScript('() => window.location.hash', '#herramientas')
        ->assertScript('() => getComputedStyle(document.documentElement).scrollBehavior', 'smooth')
        ->assertScript($landed);
});

it('takes the header section links to how it works, confidentiality and Humi', function (string $link, string $hash, bool $inResources): void {
    $page = visitPage('landing', '/');

    // Confidencialidad and Humi live inside the menu «Recursos»: it opens first.
    if ($inResources) {
        $page->click('@header-resources');
    }

    $page->click($link)->assertScript('() => window.location.hash', $hash);
})->with([
    'how it works' => ['@header-how', '#como-funciona', false],
    'confidentiality' => ['@header-privacy', '#confidencialidad', true],
    'Humi' => ['@header-humi', '#humi', true],
]);

it('lists what an inspector would ask for, one line per tool', function (): void {
    $page = visitPage('landing', '/');

    foreach (['Informe del numeral 7.7', 'Listas de verificación', 'puestos de pie', 'Bitácora de cada queja', 'Reporte de jornada para la autoridad'] as $item) {
        $page->assertSeeIn('[data-test=inspection]', $item);
    }

    $page->assertSeeIn('[data-test=inspection]', 'No sustituimos a la STPS ni certificamos que cumples con una norma.');
});

it('hides a group with fewer than 5 answers when the visitor filters like HR would', function (): void {
    visitPage('landing', '/')
        ->assertSeeIn('[data-test=privacy-demo]', 'Ambiente de trabajo')
        ->click('@privacy-area-almacen')
        ->assertSeeIn('[data-test=privacy-demo]', 'Oculto: este grupo tiene menos de 5 respuestas.')
        ->assertDontSeeIn('[data-test=privacy-demo]', 'Ambiente de trabajo')
        ->assertAttribute('@privacy-area-almacen', 'aria-pressed', 'true')
        ->click('@privacy-area-produccion')
        ->assertSeeIn('[data-test=privacy-demo]', 'Ambiente de trabajo');
});

it('shows Humi at work in a looping film, with its conversation for screen readers', function (): void {
    $video = 'document.querySelector("[data-test=humi-video] video")';

    visitPage('landing', '/')
        ->assertSeeIn('#humi', 'Pregúntale a Humi')
        ->assertScript("() => {$video}.muted && {$video}.loop && {$video}.playsInline")
        ->assertScript("() => {$video}.hasAttribute('muted')")
        ->assertScript("() => {$video}.poster.endsWith('/video/humetria-humi-light.jpg')")
        ->assertScript("() => [...{$video}.querySelectorAll('source')].map((source) => source.src.split('/').pop()).join()", 'humetria-humi-light.webm,humetria-humi-light.mp4')
        ->assertSeeIn('[data-test=humi-video] figcaption', 'Conversación de ejemplo')
        ->assertScript('() => document.querySelector("[data-test=humi-video] figcaption").textContent.includes("¿Qué me falta para estar al día en Planta Querétaro?")')
        // Once it scrolls into view it plays by itself; the button pauses it.
        ->assertScript("() => new Promise((resolve) => { {$video}.scrollIntoView(); if (! {$video}.paused) { resolve(true); } {$video}.addEventListener('playing', () => resolve(true)); })")
        ->assertAttribute('@humi-video-toggle', 'aria-label', 'Pausar video')
        ->assertAttribute('@humi-video-sound', 'aria-label', 'Activar sonido')
        ->click('@humi-video-sound')
        ->assertScript("() => ! {$video}.muted")
        ->click('@humi-video-toggle')
        ->assertScript("() => {$video}.paused");
});

it('speaks to consultants, multi-site companies and corporate groups, and to unions after them', function (): void {
    visitPage('landing', '/')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'varias empresas')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'varias sucursales, plantas u oficinas (centros de trabajo)')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'varias razones sociales')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'Cada centro recibe solo lo que le toca por su número de trabajadores.')
        ->assertSeeIn('[data-test=unions]', 'sindicato')
        ->assertScript('() => document.querySelectorAll("section[aria-labelledby=para-quien] article").length', 3)
        ->assertDontSeeIn('section[aria-labelledby="para-quien"]', 'la norma');
});

it('shows each audience as a plain row, with no icon and no card around it, separated by hairlines', function (): void {
    visitPage('landing', '/')
        // Per row: the hairline under it (none under the last) and how many icons it carries.
        ->assertScript('() => [...document.querySelectorAll("section[aria-labelledby=para-quien] article")].map((article) => getComputedStyle(article.parentElement).borderBottomWidth + "|" + article.querySelectorAll("svg").length + "|" + getComputedStyle(article).borderBottomWidth).join()', '1px|0|0px,1px|0|0px,0px|0|0px');
});

it('shows the nine tools as cards by family, each one to its own page', function (string $tool, string $hint, string $path): void {
    visitPage('landing', '/')
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test^=tool-card-]").length', 9)
        ->assertScript('() => document.querySelectorAll("#herramientas [role=tab], #herramientas [role=tabpanel]").length', 0)
        ->assertAttribute("@tool-card-{$tool}", 'href', $path)
        ->assertAttribute("@tool-card-{$tool}", 'id', "herramienta-{$tool}")
        ->assertSeeIn("[data-test=tool-card-{$tool}] [data-test=tool-hint]", $hint)
        ->assertScript('() => document.getElementById("herramientas").textContent.toLowerCase().includes("próximamente")', false);
})->with([
    'NOM-035' => ['nom_035', 'Factores de riesgo psicosocial', '/nom-035'],
    'NOM-037' => ['nom_037', 'Teletrabajo', '/nom-037'],
    'Ley Silla' => ['ley_silla', 'Asientos y descansos', '/ley-silla'],
    'complaints' => ['complaints', 'Quejas y denuncias', '/buzon-de-quejas'],
    'compensation' => ['compensation', 'Revisión salarial y contractual', '/compensacion'],
    'attendance' => ['attendance', 'Reloj checador y registro de jornada', '/registro-de-jornada'],
    'leave' => ['leave', 'Vacaciones, permisos y saldos', '/vacaciones-y-permisos'],
    'org chart' => ['org_chart', 'Quién reporta a quién', '/organigrama'],
    'engagement' => ['engagement', 'Clima laboral y pulso', '/clima-laboral'],
]);

it('takes a card to the page of its tool', function (): void {
    visitPage('landing', '/')
        ->click('@tool-card-attendance')
        ->assertPathIs('/registro-de-jornada')
        ->assertSee('Registro electrónico de jornada');
});

it('keeps the old links to a tool on the landing: the card they name is in the page', function (): void {
    visit('/#herramienta-nom_035')
        ->assertScript('() => document.getElementById("herramienta-nom_035") !== null')
        ->assertNoJavaScriptErrors();
});

it('jumps to the cards of the family a hero link names', function (string $family): void {
    visitPage('landing', '/')
        ->click("@hero-family-{$family}")
        ->assertScript('() => window.location.hash', "#familia-{$family}")
        ->assertScript("() => [...document.querySelectorAll('#familia-{$family} [data-test^=tool-card-]')].map((card) => card.id.replace('herramienta-', '')).join()", implode(',', LANDING_FAMILIES[$family]))
        ->assertScript("() => new Promise((resolve) => setTimeout(() => { const box = document.getElementById('familia-{$family}').getBoundingClientRect(); resolve(box.top < window.innerHeight && box.bottom > 0); }, 1000))");
})->with(['compliance', 'listening', 'operations']);

it('shows what the law already asks and what comes, with 2027 marked as next', function (): void {
    $page = visitPage('landing', '/');

    foreach (['NOM-035', 'Vacaciones dignas', 'NOM-037', 'Ley Silla', 'Registro de jornada', 'Jornada de 46 a 40 h'] as $milestone) {
        $page->assertSeeIn('[data-test=legal-calendar]', $milestone);
    }

    $page->assertSeeIn('[data-test=legal-calendar] [aria-current=step]', '1 de enero de 2027');
});

it('shows each tool card with a real screenshot of the product', function (): void {
    visitPage('landing', '/')
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test^=tool-card-] img").length >= 9')
        ->assertScript('() => [...document.querySelectorAll("#herramientas [data-test^=tool-card-] img")].every((img) => img.getAttribute("src").startsWith("/images/tools/"))');
});

it('answers the common questions before the demo, and links the guide of 2027', function (): void {
    visitPage('landing', '/')
        ->assertSeeIn('#preguntas', '¿Qué normas me tocan según mi tamaño?')
        ->assertSeeIn('#preguntas', '¿Humetría certifica que cumplo?')
        ->assertAttribute('#preguntas a[data-test=guide-link]', 'href', '/guias/registro-de-jornada-2027');
});

it('opens the tools on a phone from the menu button of the header', function (): void {
    visitPage('landing', '/', mobile: true)
        ->click('@tools-menu-mobile')
        ->assertAttribute('@tools-menu-mobile', 'aria-expanded', 'true')
        ->assertAttribute('#tools-menu a[href="/registro-de-jornada"]', 'href', '/registro-de-jornada')
        ->keys('@tools-menu-mobile', 'Escape')
        ->assertMissing('#tools-menu');
});

it('keeps the bar to four things and puts the blog and the roadmap in Recursos, as plain links', function (): void {
    visitPage('landing', '/')
        ->assertScript("() => document.querySelectorAll('header nav[aria-label=\"Secciones\"] > ul > li').length", 4)
        ->click('@header-resources')
        ->assertAttribute('[data-test="header-blog"]', 'href', '/blog')
        ->assertAttribute('[data-test="header-roadmap"]', 'href', '/roadmap')
        ->assertAttribute('[data-test="header-guide"]', 'href', '/guias/registro-de-jornada-2027')
        ->assertScript("() => document.querySelector('[data-test=\"header-blog\"]')?.tagName === 'A'")
        ->keys('@header-resources', 'Escape')
        ->assertMissing('#resources-menu');
});

it('reaches the blog and the roadmap from the phone menu, with room to tap them', function (): void {
    visitPage('landing', '/', mobile: true)
        ->click('@tools-menu-mobile')
        ->assertAttribute('#tools-menu a[data-test="menu-blog"]', 'href', '/blog')
        ->assertAttribute('#tools-menu a[data-test="menu-roadmap"]', 'href', '/roadmap')
        ->assertScript("() => document.querySelector('#tools-menu [data-test=\"menu-blog\"]').getBoundingClientRect().height >= 44");
});

it('lists the nine tools in the footer, each to its page', function (): void {
    visitPage('landing', '/')
        ->assertScript('() => document.querySelectorAll("footer a[href=\'/nom-035\'], footer a[href=\'/registro-de-jornada\'], footer a[href=\'/clima-laboral\']").length', 3);
});

it('states its four promises right under the hero', function (): void {
    $page = visitPage('landing', '/');

    foreach (['Enlace de un solo uso', 'RH no ve respuestas individuales', 'Grupos de 5 o más', 'No certifica el cumplimiento.'] as $promise) {
        $page->assertSeeIn('[data-test=trust]', $promise);
    }
});

it('only names NOM-035 next to NOM-037', function (): void {
    visitPage('landing', '/')
        ->assertScript('() => [...document.querySelectorAll("main section")].every((section) => !section.textContent.includes("NOM-035") || section.textContent.includes("NOM-037"))')
        ->assertScript('() => document.getElementById("agenda-una-demo-title").nextElementSibling.textContent.includes("NOM-035")', false);
});

it('never promises anonymity for identified records', function (): void {
    visitPage('landing', '/')
        ->assertSeeIn('[data-test="compliance-privacy"]', 'Guía I')
        ->assertSeeIn('[data-test="compliance-privacy"]', 'NOM-037')
        ->assertSeeIn('[data-test="compliance-privacy"]', 'roles autorizados')
        ->assertScript('() => document.querySelector("meta[name=description]").content.includes("de forma confidencial")', false);
});

it('states its legal and privacy claims exactly', function (): void {
    visitPage('landing', '/')
        ->assertSee('Elige las guías según el tamaño de cada centro')
        ->assertSee('solo con 5 o más respuestas')
        ->assertSee('con la confidencialidad que requiere cada herramienta')
        ->assertDontSee('(elige al menos una)')
        ->assertDontSee('confidencialidad que pide cada norma');
});

it('follows the system theme until the visitor picks one in the header', function (): void {
    visitPage('landing', '/', dark: true)
        ->assertScript('() => document.documentElement.classList.contains("dark")')
        ->click('@theme-menu')
        ->click('Claro')
        ->assertScript('() => document.documentElement.classList.contains("dark")', false)
        ->assertScript('() => localStorage.getItem("appearance")', 'light');
});

it('lets a phone pick the theme in the footer, where the header has no room', function (): void {
    visitPage('landing', '/', dark: true, mobile: true)
        ->assertMissing('@theme-menu')
        ->click('footer [aria-label="Claro"]')
        ->assertScript('() => document.documentElement.classList.contains("dark")', false);
});

it('tells the visitor what happens after asking for a demo', function (): void {
    $page = visitPage('landing', '/');

    foreach (['Te escribimos para agendar la llamada', 'Te mostramos Humetría con un caso como el tuyo', 'arrancas la prueba con tu gente'] as $step) {
        $page->assertSeeIn('[data-test=demo-next-steps]', $step);
    }
});

it('opens the privacy notice as a new document from the footer and from the demo form', function (string $link, bool $mobile): void {
    $page = visitPage('landing', '/', mobile: $mobile);
    $page->script('() => { window.sameDocument = true; }');

    $page->click($link)
        ->assertScript(pathBecomes('/privacy-notice'), true)
        ->assertPathIs('/privacy-notice')
        ->assertScript('() => window.sameDocument', null)
        ->assertSee('Quién es el responsable');
})->with([
    'footer' => '@footer-privacy',
    'demo form' => '@demo-privacy-link',
])->with(['desktop' => false, 'mobile' => true]);

it('takes the notice back to the sections of the landing, not to anchors of its own page', function (): void {
    $page = visitPage('privacy-notice', '/privacy-notice');

    $page->assertScript('() => [...document.querySelectorAll("footer nav a")].map((a) => a.getAttribute("href")).filter((href) => href.includes("#")).every((href) => href.startsWith("/#"))')
        ->click('footer a[href="/#herramientas"]')
        ->assertScript(pathBecomes('/'), true)
        ->assertScript('() => window.location.hash', '#herramientas');
});

it('jumps at once when the visitor asks for reduced motion', function (): void {
    visit('/', ['reducedMotion' => 'reduce'])
        ->assertScript('() => getComputedStyle(document.documentElement).scrollBehavior', 'auto');
});

it('marks in the header the section being read, and none over the hero', function (): void {
    visitPage('landing', '/')
        ->assertScript('() => document.querySelectorAll("header nav [aria-current]").length', 0)
        ->click('@header-resources')
        ->click('@header-humi')
        // The mark follows the scroll a frame later: wait for it, with the menu open again to see the item marked.
        ->assertScript('() => new Promise((resolve) => { document.querySelector("[data-test=header-resources]").click(); const marked = () => document.querySelector("[data-test=header-humi]")?.getAttribute("aria-current") === "true" ? resolve(true) : requestAnimationFrame(marked); marked(); })')
        ->assertAttributeMissing('@header-tools', 'aria-current');
});

it('leaves one of each tag of the head after the app starts, as many as the server printed', function (string $component, string $url): void {
    $counts = <<<'JS'
        () => JSON.stringify(Object.fromEntries([
            'title',
            'meta[name=description]',
            'link[rel=canonical]',
            'script[type="application/ld+json"]',
            'meta[property="og:title"]',
            'meta[property="og:image"]',
            'meta[name="twitter:card"]',
            'meta[name="app-revision"]',
        ].map((selector) => [selector, document.head.querySelectorAll(selector).length])))
        JS;

    visitPage($component, $url)
        ->assertScript($counts, json_encode([
            'title' => 1,
            'meta[name=description]' => 1,
            'link[rel=canonical]' => 1,
            'script[type="application/ld+json"]' => 1,
            'meta[property="og:title"]' => 1,
            'meta[property="og:image"]' => 1,
            'meta[name="twitter:card"]' => 1,
            'meta[name="app-revision"]' => 1,
        ], JSON_UNESCAPED_SLASHES))
        ->assertScript('() => document.head.querySelector("link[rel=canonical]").href', rtrim((string) config('app.url'), '/').parse_url($url, PHP_URL_PATH))
        ->assertScript('() => document.querySelector("meta[property=\"og:url\"]").content === document.head.querySelector("link[rel=canonical]").href', true)
        ->assertScript('() => document.title.endsWith(" - Humetría")', $url !== '/')
        ->assertScript('() => document.title.startsWith("Humetría")', $url === '/');
})->with([['landing', '/'], ['privacy-notice', '/privacy-notice']]);

it('reveals the blocks below the fold as they scroll into view, and never hides what is already in view', function (): void {
    $count = fn (string $state): string => "document.querySelectorAll('[data-reveal=\"{$state}\"]').length";
    // Hydrating hides what sits below the fold: wait for it, the effect runs after the first paint.
    $hidden = "() => new Promise((resolve) => { const wait = () => {$count('pending')} > 0 ? resolve(true) : requestAnimationFrame(wait); wait(); })";
    $revealAll = <<<JS
        () => new Promise((resolve) => {
            document.querySelectorAll('[data-reveal="pending"]').forEach((node) => node.scrollIntoView({ block: 'center', behavior: 'instant' }));
            const wait = () => {$count('pending')} === 0 ? resolve(true) : requestAnimationFrame(wait);
            wait();
        })
        JS;

    visit('/')
        ->assertScript($hidden)
        ->assertScript("() => [...document.querySelectorAll('[data-reveal=\"pending\"]')].every((node) => getComputedStyle(node).opacity === '0' || node.classList.contains('reveal-bar'))")
        // The hero is in view when the page opens: it animates on its own and is never hidden by the scroll reveal.
        ->assertScript("() => document.querySelector('#inicio [data-reveal=\"pending\"]') === null")
        ->assertScript($revealAll)
        ->assertScript("() => {$count('done')} > 0")
        ->assertNoJavaScriptErrors();
});

it('shows what scrolls into view with a fade only, no movement, when the visitor asks for reduced motion', function (): void {
    visit('/', ['reducedMotion' => 'reduce'])
        ->assertScript("() => new Promise((resolve) => { const wait = () => document.querySelectorAll('[data-reveal=\"pending\"]').length > 0 ? resolve(true) : requestAnimationFrame(wait); wait(); })")
        ->assertScript("() => [...document.querySelectorAll('[data-reveal=\"pending\"]')].every((node) => getComputedStyle(node).transform === 'none' && getComputedStyle(node).filter === 'none')")
        ->assertNoJavaScriptErrors();
});

it('fills the rail beside the steps as they scroll by on a phone', function (): void {
    $progress = "Number(document.querySelector('#seccion-como-funciona [class*=\"scaleY\"]').style.getPropertyValue('--p'))";
    // The page is hydrated once the scroll reveal has hidden what is below the fold; then it scrolls to the end of the steps and waits for the fill.
    $filled = <<<JS
        () => new Promise((resolve) => {
            const start = performance.now();
            const hydrated = () => document.querySelector('[data-reveal="pending"]') !== null;
            const wait = () => {$progress} > 0.3 ? resolve(true) : performance.now() - start > 5000 ? resolve(false) : requestAnimationFrame(wait);
            const begin = () => {
                if (!hydrated()) { return requestAnimationFrame(begin); }
                document.querySelector('#seccion-como-funciona ol').scrollIntoView({ block: 'end', behavior: 'instant' });
                wait();
            };
            begin();
        })
        JS;

    visit('/')->on()->mobile()
        ->assertScript($filled)
        ->assertNoJavaScriptErrors();
});

it('enters the headline word by word and the parts under it in a staggered fade, with CSS', function (): void {
    $animation = fn (string $selector): string => "() => JSON.stringify([...document.querySelectorAll({$selector})].map((node) => { const style = getComputedStyle(node); return [style.animationName, style.animationDuration, style.animationDelay, style.animationFillMode]; }))";
    $words = array_map(fn (int $index): array => ['enter', '0.7s', (0.1 + $index * 0.05).'s', 'both'], range(0, 11));
    $after = [['enter', '0.6s', '0.55s', 'both'], ['enter', '0.6s', '0.65s', 'both'], ['enter', '0.6s', '0.2s', 'both'], ['enter', '0.6s', '0.75s', 'both']];

    visit('/')
        ->assertScript($animation("'#hero-title span'"), json_encode($words, JSON_THROW_ON_ERROR))
        ->assertScript($animation("'#inicio .enter-after'"), json_encode($after, JSON_THROW_ON_ERROR))
        ->assertNoJavaScriptErrors();
});

it('enters the hero without moving when the visitor asks for reduced motion', function (): void {
    visit('/', ['reducedMotion' => 'reduce'])
        ->assertScript("() => [...document.querySelectorAll('#hero-title span, #inicio .enter-after')].every((node) => getComputedStyle(node).getPropertyValue('--from-transform').trim() === 'none' && getComputedStyle(node).getPropertyValue('--from-filter').trim() !== 'blur(6px)')")
        ->assertNoJavaScriptErrors();
});

it('keeps a space between the words of the headline, though each one is its own inline block', function (): void {
    // A plain space at the end of an inline block collapses: only a no-break space keeps the words apart.
    visit('/')
        ->assertScript("() => [...document.querySelectorAll('#hero-title span')].slice(0, -1).every((word) => word.textContent.endsWith('\\u00A0'))")
        ->assertScript("() => document.querySelector('#hero-title').innerText.replace(/\\u00A0/g, ' ').includes('Cumple la ley y cuida a tu gente')");
});

it('links the configured social profiles in the footer, as plain links that open in a new tab', function (): void {
    config(['app.social' => [
        'linkedin' => 'https://www.linkedin.com/company/humetria',
        'facebook' => null,
        'instagram' => 'https://www.instagram.com/humetria',
    ]]);

    visit('/')
        ->assertScript("() => [...document.querySelectorAll('footer [aria-label=\"Redes sociales\"] a')].map((a) => a.getAttribute('href') + '|' + a.target + '|' + a.rel).join(',')", 'https://www.linkedin.com/company/humetria|_blank|noopener noreferrer,https://www.instagram.com/humetria|_blank|noopener noreferrer')
        ->assertNoJavaScriptErrors();
});

it('shows no social list in the footer while none is configured', function (): void {
    config(['app.social' => ['linkedin' => null, 'facebook' => null, 'instagram' => null]]);

    visit('/')
        ->assertScript("() => document.querySelector('footer [aria-label=\"Redes sociales\"]') === null");
});

it('keeps the logo, the two ways in and the tools menu in the header of a 375 px phone', function (string $url): void {
    // Reduced motion: the steps play on their own, and the resize waits for nothing to be moving.
    resizeTo(visit($url, ['reducedMotion' => 'reduce'])->assertNoJavaScriptErrors(), 375, 812)
        ->assertScript('() => document.querySelector("header a[href=\'/\'], header a[aria-label=\'Humetría, inicio\']").getBoundingClientRect().width >= 32', true)
        ->assertScript('() => document.documentElement.scrollWidth', 375)
        ->assertVisible('@sign-in')
        ->assertVisible('@header-demo')
        ->assertVisible('@tools-menu-mobile');
})->with(['home' => '/', 'tool page' => '/nom-035']);

it('links the footer to the blog with a plain link, so every page of the site hands its weight to it', function (): void {
    visit('/')
        ->assertScript("() => document.querySelector('[data-test=\"footer-blog\"]')?.tagName === 'A' && document.querySelector('[data-test=\"footer-blog\"]').getAttribute('href') === '/blog'")
        ->assertNoJavaScriptErrors();
});
