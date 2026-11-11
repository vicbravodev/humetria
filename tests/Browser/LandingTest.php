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
        'profile' => 'Elige para quién es Humetría.',
        'tools' => 'Elige al menos una herramienta.',
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

it('asks who Humetría is for, unions included', function (): void {
    visit('/')
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
        ->assertTitleContains('Herramientas de RH')
        ->assertSeeIn('#hero-title', 'Del celular de tu gente al expediente de la STPS')
        ->assertScript('() => ["NOM-035", "NOM-037", "Ley Silla", "quejas", "Compensación", "jornada", "Vacaciones", "Organigrama", "Clima"].every((tool) => document.querySelector("meta[name=description]").content.includes(tool))')
        ->assertScript('() => [...document.querySelectorAll("[data-test=hero-tools] li")].map((item) => item.textContent).join(" · ")', 'NOM-035 · NOM-037 · Ley Silla · Buzón de quejas · Compensación · Registro de jornada · Vacaciones y permisos · Organigrama · Clima y pulso')
        ->assertDontSeeIn('#hero-title', 'NOM-035');
});

it('plays the hero film muted and in a loop, and lets the visitor pause it', function (): void {
    $video = 'document.querySelector("[data-test=hero-video] video")';

    visitPage('landing', '/')
        ->assertScript("() => {$video}.muted && {$video}.loop && {$video}.playsInline")
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

it('keeps the hero film still when the visitor asks for reduced motion', function (): void {
    visit('/', ['reducedMotion' => 'reduce'])
        ->assertScript('() => document.querySelector("[data-test=hero-video] video").autoplay', false)
        ->assertScript('() => document.querySelector("[data-test=hero-video] video").paused')
        ->assertAttribute('@hero-video-toggle', 'aria-label', 'Reproducir video')
        ->assertNoJavaScriptErrors();
});

it('takes the header tools link to the tools section', function (): void {
    // The link shows from the md breakpoint up: on a phone the header only fits the logo and the two calls to action.
    // The page glides until the section lands right under the 64 px sticky header (`scroll-mt-16`).
    $sectionTop = '() => Math.abs(document.getElementById("herramientas").getBoundingClientRect().top - 64) < 2';
    $landed = '() => new Promise((resolve) => { const settled = () => Math.abs(document.getElementById("herramientas").getBoundingClientRect().top - 64) < 2 ? resolve(true) : requestAnimationFrame(settled); settled(); })';

    visitPage('landing', '/')
        ->assertScript($sectionTop, false)
        ->click('@header-tools')
        ->assertScript('() => window.location.hash', '#herramientas')
        ->assertScript('() => getComputedStyle(document.documentElement).scrollBehavior', 'smooth')
        ->assertScript($landed);
});

it('takes the header section links to how it works, confidentiality and Humi', function (string $link, string $hash): void {
    visitPage('landing', '/')
        ->click($link)
        ->assertScript('() => window.location.hash', $hash);
})->with([
    'how it works' => ['@header-how', '#como-funciona'],
    'confidentiality' => ['@header-privacy', '#confidencialidad'],
    'Humi' => ['@header-humi', '#humi'],
]);

it('shows one tool at a time on a large stage, and a tab switches it', function (string $tool, string $hint, string $text): void {
    $panel = 'document.querySelector("#herramientas [data-test=tool-panel]")';

    visitPage('landing', '/')
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test=tool-panel]").length', 1)
        ->assertScript('() => document.querySelectorAll("#herramientas [role=tab]").length', 9)
        ->click("@tool-tab-{$tool}")
        ->assertAttribute("@tool-tab-{$tool}", 'aria-selected', 'true')
        ->assertScript('() => window.location.hash', "#herramienta-{$tool}")
        ->assertScript("() => document.querySelector('[data-test=tool-tab-{$tool}] [data-test=tool-hint]').textContent", $hint)
        ->assertScript("() => {$panel}.textContent.includes(".json_encode($text).')')
        ->assertScript('() => document.getElementById("herramientas").textContent.toLowerCase().includes("próximamente")', false);
})->with([
    'NOM-035' => ['nom_035', 'Factores de riesgo psicosocial', 'numeral 7.7'],
    'NOM-037' => ['nom_037', 'Teletrabajo', 'lista de verificación'],
    'Ley Silla' => ['ley_silla', 'Asientos y descansos', 'reglamento interior'],
    'complaints' => ['complaints', 'Quejas y denuncias', 'nunca a la persona señalada'],
    'compensation' => ['compensation', 'Revisión salarial y contractual', 'sindicato'],
    'attendance' => ['attendance', 'Reloj checador y registro de jornada', '1 de enero de 2027'],
    'leave' => ['leave', 'Vacaciones, permisos y saldos', 'saldo'],
    'org chart' => ['org_chart', 'Quién reporta a quién', 'organigrama'],
    'engagement' => ['engagement', 'Clima laboral y pulso', 'Nadie ve una respuesta individual'],
]);

it('opens on the tool the link names', function (): void {
    $page = visitPage('landing', '/');
    $page->script('() => { window.location.hash = "herramienta-engagement"; }');

    $page->assertAttribute('@tool-tab-engagement', 'aria-selected', 'true')
        ->assertSeeIn('[data-test=tool-panel]', 'Nadie ve una respuesta individual');
});

it('keeps the selected tool visible in the phone strip when the hash changes', function (): void {
    $page = visitPage('landing', '/', mobile: true);
    $page->script('() => { window.location.hash = "herramienta-engagement"; }');

    $page->assertAttribute('@tool-tab-engagement', 'aria-selected', 'true')
        ->assertScript('() => { const tab = document.getElementById("tool-tab-engagement").getBoundingClientRect(); const strip = document.querySelector("#herramientas [role=tablist]").getBoundingClientRect(); return tab.left >= strip.left && tab.right <= strip.right; }')
        ->keys('@tool-tab-engagement', 'Home')
        ->assertAttribute('@tool-tab-nom_035', 'aria-selected', 'true')
        ->assertScript('() => { const tab = document.getElementById("tool-tab-nom_035").getBoundingClientRect(); const strip = document.querySelector("#herramientas [role=tablist]").getBoundingClientRect(); return tab.left >= strip.left && tab.right <= strip.right; }')
        ->assertNoJavaScriptErrors();
});

it('fits every complete tool presentation below the laptop header', function (): void {
    $page = resizeTo(visitPage('landing', '/'), 1512, 982);

    foreach (['nom_035', 'nom_037', 'ley_silla', 'complaints', 'compensation', 'attendance', 'leave', 'org_chart', 'engagement'] as $tool) {
        $page->click("@tool-tab-{$tool}");
        $page->script('() => { document.getElementById("herramientas").scrollIntoView({ behavior: "instant", block: "start" }); }');
        $page->assertScript('() => { const panel = document.getElementById("tool-panel").getBoundingClientRect(); const tabs = document.querySelector("#herramientas [role=tablist]").getBoundingClientRect(); return panel.top >= 64 && panel.bottom <= window.innerHeight && tabs.top >= 64 && tabs.bottom <= window.innerHeight; }');
    }

    $page->assertNoJavaScriptErrors();
});

it('keeps the phone playback controls inside the video and respects reduced motion', function (): void {
    $page = resizeTo(visit('/', ['reducedMotion' => 'reduce'])->assertNoJavaScriptErrors(), 375, 812);
    $video = 'document.querySelector("[data-test=tool-film] video")';

    $page->assertScript("() => {$video}.paused && ! {$video}.autoplay")
        ->assertAttribute('@tool-film-toggle', 'aria-label', 'Reproducir video')
        ->assertScript('() => { const video = document.querySelector("[data-test=tool-film] video").getBoundingClientRect(); const control = document.querySelector("[data-test=tool-film-toggle]").getBoundingClientRect(); return control.left >= video.left && control.right <= video.right && control.top >= video.top && control.bottom <= video.bottom && control.width >= 44 && control.height >= 44; }')
        ->click('@tool-film-toggle')
        ->assertAttribute('@tool-film-toggle', 'aria-label', 'Pausar video')
        ->click('@tool-film-toggle')
        ->assertScript("() => {$video}.paused")
        ->assertNoJavaScriptErrors();
});

it('moves between tabs with the arrow keys', function (): void {
    visitPage('landing', '/')
        ->assertAttribute('@tool-tab-nom_035', 'aria-selected', 'true')
        ->click('@tool-tab-nom_035')
        ->keys('@tool-tab-nom_035', 'ArrowRight')
        ->assertAttribute('@tool-tab-nom_037', 'aria-selected', 'true')
        ->keys('@tool-tab-nom_037', 'ArrowLeft')
        ->assertAttribute('@tool-tab-nom_035', 'aria-selected', 'true');
});

it('lists what an inspector would ask for, one line per tool', function (): void {
    $page = visitPage('landing', '/');

    foreach (['Informe del numeral 7.7', 'Listas de verificación', 'puestos de pie', 'Bitácora de cada queja', 'Reporte de jornada para la autoridad'] as $item) {
        $page->assertSeeIn('[data-test=inspection]', $item);
    }

    $page->assertSeeIn('[data-test=inspection]', 'No certifica el cumplimiento de ninguna norma.');
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

it('shows each tool as a narrated film with chapters, and a chapter jumps to the moment its narration starts', function (string $tool, string $film, float $third, float $fourth): void {
    $video = 'document.querySelector("#herramientas [data-test=tool-film] video")';

    $page = visitPage('landing', '/');
    $page->click("@tool-tab-{$tool}")
        ->assertScript("() => {$video}.muted && {$video}.loop && {$video}.playsInline")
        ->assertScript("() => {$video}.poster.endsWith('/video/{$film}-light.jpg')")
        ->assertScript("() => [...{$video}.querySelectorAll('source')].map((source) => source.src.split('/').pop()).join()", "{$film}-light.webm,{$film}-light.mp4")
        // Large enough to read what the film shows (at 1280 px the stage is wider than 700 px).
        ->assertScript("() => {$video}.getBoundingClientRect().width > 700")
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test^=tool-chapter-]").length', 4)
        ->assertScript('() => document.querySelector("#herramientas [data-test=tool-film] figcaption").textContent.length > 40');

    $page->click('@tool-chapter-2')
        ->assertScript("() => new Promise((resolve) => { const there = () => {$video}.currentTime >= {$third} && {$video}.currentTime < {$fourth} ? resolve(true) : requestAnimationFrame(there); there(); })")
        ->assertAttribute('@tool-chapter-2', 'aria-current', 'true')
        ->assertScript("() => new Promise((resolve) => { const playing = () => ! {$video}.paused ? resolve(true) : requestAnimationFrame(playing); playing(); })")
        ->assertAttribute('@tool-film-toggle', 'aria-label', 'Pausar video')
        // It starts muted; the visitor turns the narration on and off.
        ->assertAttribute('@tool-film-sound', 'aria-label', 'Activar sonido')
        ->click('@tool-film-sound')
        ->assertScript("() => ! {$video}.muted")
        ->assertAttribute('@tool-film-sound', 'aria-label', 'Silenciar video')
        ->click('@tool-film-sound')
        ->assertScript("() => {$video}.muted")
        ->click('@tool-film-toggle')
        ->assertScript("() => {$video}.paused");
})->with([
    'NOM-035' => ['nom_035', 'humetria-nom-035', 13.77, 21.47],
    'NOM-037' => ['nom_037', 'humetria-nom-037', 12.4, 19.9],
    'Ley Silla' => ['ley_silla', 'humetria-ley-silla', 14.2, 21.23],
    'complaints' => ['complaints', 'humetria-complaints', 13.6, 19.93],
    'compensation' => ['compensation', 'humetria-compensation', 15.8, 21.63],
    'attendance' => ['attendance', 'humetria-attendance', 14.33, 20.97],
    'leave' => ['leave', 'humetria-leave', 11.8, 17.33],
    'org chart' => ['org_chart', 'humetria-org-chart', 11.67, 17.33],
    'engagement' => ['engagement', 'humetria-engagement', 14.37, 21.57],
]);

it('shows the film drawn with the dark tokens in the dark theme', function (): void {
    $video = 'document.querySelector("#herramientas [data-test=tool-film] video")';

    visitPage('landing', '/', dark: true)
        ->assertScript("() => {$video}.poster.endsWith('/video/humetria-nom-035.jpg')")
        ->assertScript("() => [...{$video}.querySelectorAll('source')].map((source) => source.src.split('/').pop()).join()", 'humetria-nom-035.webm,humetria-nom-035.mp4');
});

it('keeps the film of the time clock on the QR and passkey flow', function (): void {
    visitPage('landing', '/')
        ->click('@tool-tab-attendance')
        ->assertScript('() => document.querySelector("#herramientas [data-test=tool-film] figcaption").textContent.includes("código QR")')
        ->assertScript('() => document.querySelector("#herramientas [data-test=tool-film] figcaption").textContent.includes("reporte para la autoridad")');
});

it('speaks to consultants, multi-site companies and corporate groups, and to unions after them', function (): void {
    visitPage('landing', '/')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'varias empresas')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'varios centros de trabajo')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'varias razones sociales')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'Humetría le asigna a cada uno lo que le toca según su tamaño y su gente.')
        ->assertSeeIn('[data-test=unions]', 'sindicato')
        ->assertScript('() => document.querySelectorAll("section[aria-labelledby=para-quien] article").length', 3)
        ->assertDontSeeIn('section[aria-labelledby="para-quien"]', 'la norma');
});

it('lists the audiences plainly, so only the tools read as cards', function (): void {
    visitPage('landing', '/')
        ->assertScript('() => [...document.querySelectorAll("section[aria-labelledby=para-quien] article")].map((article) => getComputedStyle(article).backgroundColor + "|" + getComputedStyle(article).borderBottomWidth + "|" + article.querySelectorAll("span svg").length).join()', 'rgba(0, 0, 0, 0)|0px|0,rgba(0, 0, 0, 0)|0px|0,rgba(0, 0, 0, 0)|0px|0');
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
        ->assertSee('Genera el informe del numeral 7.7')
        ->assertSee('solo con 5 o más respuestas')
        ->assertSee('con la confidencialidad que requiere cada herramienta')
        ->assertSee('¿Qué herramientas te interesan? (elige al menos una)')
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

it('jumps at once when the visitor asks for reduced motion', function (): void {
    visit('/', ['reducedMotion' => 'reduce'])
        ->assertScript('() => getComputedStyle(document.documentElement).scrollBehavior', 'auto');
});

it('marks in the header the section being read, and none over the hero', function (): void {
    visitPage('landing', '/')
        ->assertScript('() => document.querySelectorAll("header nav [aria-current]").length', 0)
        ->click('@header-humi')
        // The mark follows the scroll a frame later: wait for it.
        ->assertScript('() => new Promise((resolve) => { const marked = () => document.querySelector("[data-test=header-humi]").getAttribute("aria-current") === "true" ? resolve(true) : requestAnimationFrame(marked); marked(); })')
        ->assertAttributeMissing('@header-tools', 'aria-current');
});
