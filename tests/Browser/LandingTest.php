<?php

declare(strict_types=1);

use App\Modules\Demos\Models\DemoRequest;

/** The tools section groups the nine tools in three families, in this order. */
const LANDING_FAMILIES = [
    'compliance' => ['nom_035', 'nom_037', 'ley_silla', 'attendance'],
    'listening' => ['complaints', 'engagement'],
    'operations' => ['leave', 'org_chart', 'compensation'],
];

/** The family whose tab shows a tool's tab. */
function landingFamilyOf(string $tool): string
{
    foreach (LANDING_FAMILIES as $family => $tools) {
        if (in_array($tool, $tools, true)) {
            return $family;
        }
    }

    throw new InvalidArgumentException("No family has the tool {$tool}.");
}

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
        ->assertTitleContains('NOM-035, NOM-037 y Ley Silla')
        ->assertSeeIn('#hero-title', 'Tu gente contesta desde el celular. Tú llegas listo a la STPS.')
        ->assertScript('() => ["NOM-035", "NOM-037", "Ley Silla", "quejas", "Compensación", "jornada", "Vacaciones", "Organigrama", "Clima"].every((tool) => document.querySelector("meta[name=description]").content.includes(tool))')
        ->assertScript('() => [...document.querySelectorAll("[data-test=hero-families] a b")].map((family) => family.textContent).join(" · ")', 'Cumple la norma · Escucha a tu gente · Lleva el día a día')
        ->assertScript('() => ["NOM-035", "NOM-037", "Ley Silla", "Registro de jornada", "Buzón de quejas", "Clima y pulso", "Vacaciones y permisos", "Organigrama", "Compensación"].every((tool) => document.querySelector("[data-test=hero-families]").textContent.includes(tool))')
        ->assertDontSeeIn('#hero-title', 'NOM-035');
});

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

it('shows one tool at a time on a large stage, and a family and a tab switch it', function (string $tool, string $hint, string $text): void {
    $panel = 'document.querySelector("#herramientas [data-test=tool-panel]")';
    $family = landingFamilyOf($tool);

    visitPage('landing', '/')
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test=tool-panel]").length', 1)
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test^=family-tab-]").length', 3)
        ->click("@family-tab-{$family}")
        ->assertAttribute("@family-tab-{$family}", 'aria-selected', 'true')
        ->assertScript("() => [...document.querySelectorAll('[data-test=tool-rail] [role=tab]')].length", count(LANDING_FAMILIES[$family]))
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

it('keeps the nine tools in the page as tab panels and shows only the chosen one', function (): void {
    $panels = '[...document.querySelectorAll("#herramientas [role=tabpanel]")]';
    $page = visitPage('landing', '/');

    $page->assertScript("() => {$panels}.length", 9)
        ->assertScript("() => {$panels}.filter((panel) => getComputedStyle(panel).display !== 'none').length", 1)
        ->assertScript('() => document.querySelector("#herramientas [data-test=tool-panel]").id', 'tool-panel-nom_035')
        ->assertScript('() => document.querySelector("#herramientas [data-test=tool-panel]").getAttribute("aria-labelledby")', 'tool-panel-title-nom_035')
        ->assertScript('() => document.getElementById("tool-tab-nom_035").getAttribute("aria-controls")', 'tool-panel-nom_035')
        ->assertScript("() => new Set({$panels}.map((panel) => panel.id)).size", 9)
        ->assertScript("() => {$panels}.every((panel) => document.getElementById(panel.getAttribute('aria-labelledby')) !== null)")
        ->assertNoJavaScriptErrors();
});

it('reveals the panel of each tab and hides the one before', function (string $family, string $tool, string $before): void {
    $display = fn (string $key): string => "() => getComputedStyle(document.getElementById('tool-panel-{$key}')).display";

    visitPage('landing', '/')
        ->click("@family-tab-{$family}")
        ->click("@tool-tab-{$tool}")
        ->assertScript($display($tool), 'grid')
        ->assertScript($display($before), 'none')
        ->assertScript('() => document.querySelector("#herramientas [data-test=tool-panel]").id', "tool-panel-{$tool}")
        ->assertScript('() => document.querySelectorAll("#herramientas [data-test=tool-film]").length', 1)
        ->assertScript("() => document.querySelector('#tool-panel-{$tool} [data-test=tool-film] video') !== null");
})->with([
    'NOM-037' => ['compliance', 'nom_037', 'nom_035'],
    'complaints' => ['listening', 'complaints', 'nom_035'],
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
        ->assertScript('() => { const tab = document.getElementById("tool-tab-engagement").getBoundingClientRect(); const strip = document.querySelector("[data-test=tool-rail]").getBoundingClientRect(); return tab.left >= strip.left && tab.right <= strip.right; }')
        ->keys('@tool-tab-engagement', 'Home')
        ->assertAttribute('@tool-tab-complaints', 'aria-selected', 'true')
        ->assertScript('() => { const tab = document.getElementById("tool-tab-complaints").getBoundingClientRect(); const strip = document.querySelector("[data-test=tool-rail]").getBoundingClientRect(); return tab.left >= strip.left && tab.right <= strip.right; }')
        ->assertNoJavaScriptErrors();
});

it('fits every complete tool presentation below the laptop header, under its tools', function (): void {
    $page = resizeTo(visitPage('landing', '/'), 1512, 982);

    foreach (LANDING_FAMILIES as $family => $tools) {
        $page->click("@family-tab-{$family}");

        foreach ($tools as $tool) {
            $page->click("@tool-tab-{$tool}");
            // The family's tools just under the 64 px header, the way a visitor scrolls to them: the stage fits below them.
            $page->script('() => { window.scrollTo({ top: document.querySelector("[data-test=tool-rail]").getBoundingClientRect().top + window.scrollY - 80, behavior: "instant" }); }');
            $page->assertScript('() => { const panel = document.querySelector("#herramientas [data-test=tool-panel]").getBoundingClientRect(); const rail = document.querySelector("[data-test=tool-rail]").getBoundingClientRect(); return rail.top >= 64 && rail.bottom <= panel.top && panel.bottom <= window.innerHeight; }');
        }
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

it('shows each tool as a narrated film with chapters, and a chapter jumps to the moment its narration starts', function (string $tool, string $film, float $third, float $fourth): void {
    $video = 'document.querySelector("#herramientas [data-test=tool-film] video")';

    $page = visitPage('landing', '/');
    $page->click('@family-tab-'.landingFamilyOf($tool))
        ->click("@tool-tab-{$tool}")
        ->assertScript("() => {$video}.muted && {$video}.loop && {$video}.playsInline")
        ->assertScript("() => {$video}.hasAttribute('muted')")
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
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'varias sucursales, plantas u oficinas (centros de trabajo)')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'varias razones sociales')
        ->assertSeeIn('section[aria-labelledby="para-quien"]', 'Cada centro recibe solo lo que le toca por su número de trabajadores.')
        ->assertSeeIn('[data-test=unions]', 'sindicato')
        ->assertScript('() => document.querySelectorAll("section[aria-labelledby=para-quien] article").length', 3)
        ->assertDontSeeIn('section[aria-labelledby="para-quien"]', 'la norma');
});

it('shows each audience as a card with its icon', function (): void {
    visitPage('landing', '/')
        ->assertScript('() => [...document.querySelectorAll("section[aria-labelledby=para-quien] article")].map((article) => getComputedStyle(article).borderBottomWidth + "|" + article.querySelectorAll("span svg").length).join()', '1px|1,1px|1,1px|1');
});

it('opens the tools section on the first tool of the family a hero link names', function (string $family, string $tool): void {
    visitPage('landing', '/')
        ->click("@hero-family-{$family}")
        ->assertAttribute("@family-tab-{$family}", 'aria-selected', 'true')
        ->assertAttribute("@tool-tab-{$tool}", 'aria-selected', 'true')
        ->assertScript('() => new Promise((resolve) => setTimeout(() => { const box = document.getElementById("herramientas").getBoundingClientRect(); resolve(box.top < window.innerHeight / 2 && box.bottom > 0); }, 1000))');
})->with([
    'compliance' => ['compliance', 'nom_035'],
    'listening' => ['listening', 'complaints'],
    'operations' => ['operations', 'leave'],
]);

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
        ->assertSee('Arma el informe que pide la norma (numeral 7.7)')
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
        ->click('@header-humi')
        // The mark follows the scroll a frame later: wait for it.
        ->assertScript('() => new Promise((resolve) => { const marked = () => document.querySelector("[data-test=header-humi]").getAttribute("aria-current") === "true" ? resolve(true) : requestAnimationFrame(marked); marked(); })')
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
        ->assertScript('() => document.title.endsWith(" - Humetría")', true);
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

it('plays the details of a tool again each time the tool changes', function (): void {
    $details = "document.querySelector('#herramientas [role=tabpanel] .enter-details')";
    // The key remounts the details with the tool: a new element, whose CSS animation starts over.
    $replayed = <<<JS
        () => new Promise((resolve) => {
            const before = {$details};
            document.querySelector('[data-test=tool-tab-ley_silla]').click();
            const wait = () => {
                const after = {$details};
                after !== null && after !== before ? resolve(after.getAnimations().some((animation) => animation.animationName === 'enter')) : requestAnimationFrame(wait);
            };
            wait();
        })
        JS;

    visit('/#herramientas')
        ->assertScript("() => getComputedStyle({$details}).animationName", 'enter')
        ->assertScript($replayed)
        ->assertNoJavaScriptErrors();
});

it('keeps a space between the words of the headline, though each one is its own inline block', function (): void {
    // A plain space at the end of an inline block collapses: only a no-break space keeps the words apart.
    visit('/')
        ->assertScript("() => [...document.querySelectorAll('#hero-title span')].slice(0, -1).every((word) => word.textContent.endsWith('\\u00A0'))")
        ->assertScript("() => document.querySelector('#hero-title').innerText.replace(/\\u00A0/g, ' ').includes('Tu gente contesta desde el celular.')");
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
