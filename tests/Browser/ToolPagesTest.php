<?php

declare(strict_types=1);

use App\Http\ToolPages;
use App\Modules\Demos\Models\DemoRequest;

/*
 * The page of each tool and the guide: their health in every look, their film, their questions and the demo form that
 * starts with the tool chosen and comes back to the same page.
 */

$looks = [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
];

it('shows every tool page healthy, with its h1, its questions and its form', function (string $key, string $slug): void {
    $page = ToolPages::page($key);

    visitPage('tool', "/{$slug}")
        ->assertScript('() => document.querySelector("h1").textContent', $page['h1'])
        ->assertSee($page['faqs'][0]['question'])
        ->assertScript('() => document.querySelectorAll("h1").length', 1)
        ->assertScript("() => document.querySelector('[data-test=tools-group] input[value={$key}]').checked")
        ->assertScript('() => document.querySelector("#demo-request-form input[name=page]").value', ToolPages::route($key))
        ->assertScript('() => document.querySelectorAll("#otras-herramientas a").length', 3);
})->with(fn (): array => array_map(fn (string $key): array => [$key, ToolPages::SLUGS[$key]], array_combine(array_keys(ToolPages::SLUGS), array_keys(ToolPages::SLUGS))));

it('looks right in light, dark and on a phone', function (bool $dark, bool $mobile): void {
    visitPage('tool', '/registro-de-jornada', $dark, $mobile)
        ->assertSee('Qué pide la ley')
        ->assertSee('Cómo funciona')
        ->assertSee('Del reloj checador al registro que pide la ley')
        ->assertSee('Para quién es')
        ->assertSee('Lo que se registra, y lo que no')
        ->assertSee('Preguntas frecuentes');
})->with($looks);

it('shows each screenshot in the theme of the page', function (bool $dark, string $theme): void {
    visitPage('tool', '/registro-de-jornada', $dark)
        ->assertScript("() => [...document.querySelectorAll('main figure img[src^=\"/images/tools/attendance-02-people\"]')].filter((img) => img.offsetParent !== null).map((img) => img.getAttribute('src')).join()", "/images/tools/attendance-02-people-{$theme}.webp");
})->with([
    'light' => [false, 'light'],
    'dark' => [true, 'dark'],
]);

it('answers the question of the page by choice', function (): void {
    visitPage('tool', '/nom-035')
        ->assertSeeIn('@pick-answer', 'Solo la Guía I')
        ->click('@pick-2')
        ->assertAttribute('@pick-2', 'aria-pressed', 'true')
        ->assertSeeIn('@pick-answer', 'Guías I y III');
});

it('says the fingerprint of the person never reaches Humetría', function (): void {
    visitPage('tool', '/registro-de-jornada')
        ->assertSee('Humetría nunca la recibe ni la guarda.');
});

it('opens the tools from the header and goes to another tool', function (): void {
    visitPage('tool', '/registro-de-jornada')
        ->click('@tools-menu')
        ->assertAttribute('@tools-menu', 'aria-expanded', 'true')
        ->click('#tools-menu a[href="/vacaciones-y-permisos"]')
        ->assertPathIs('/vacaciones-y-permisos');
});

it('opens an answer when its question is clicked', function (): void {
    $faq = ToolPages::page('attendance')['faqs'][1];

    visitPage('tool', '/registro-de-jornada')
        ->assertDontSee($faq['answer'])
        ->click($faq['question'])
        ->assertSee($faq['answer']);
});

it('shows the guide healthy in every look, with the way to the tool', function (bool $dark, bool $mobile): void {
    visitPage('guide', '/guias/registro-de-jornada-2027', $dark, $mobile)
        ->assertScript('() => document.querySelector("h1").textContent', ToolPages::guide()['h1'])
        ->assertSee('Fuente: LFT, art. 132, fracción XXXIV')
        ->assertAttribute('a[data-track-target=guide_tool]', 'href', '/registro-de-jornada');
})->with($looks);

it('takes a demo asked from a tool page back to that page, with the request stored', function (): void {
    visitPage('tool', '/registro-de-jornada')
        ->type('#demo-name', 'Ana Prueba')
        ->type('#demo-email', 'ana@empresa.mx')
        ->type('#demo-company', 'Empresa Ejemplo')
        ->type('#demo-whatsapp', '5512345678')
        ->click('@demo-request-submit')
        ->assertSee('Recibimos tu solicitud')
        ->assertPathIs('/registro-de-jornada');

    expect(DemoRequest::query()->sole()->tools->map->value->all())->toBe(['attendance']);
});

it('shows each tool as a narrated film with chapters, and a chapter jumps to the moment its narration starts', function (string $slug, string $film, float $third, float $fourth): void {
    $video = 'document.querySelector("[data-test=tool-film] video")';

    $page = visitPage('tool', "/{$slug}");
    $page->assertScript("() => {$video}.muted && {$video}.loop && {$video}.playsInline")
        ->assertScript("() => {$video}.poster.endsWith('/video/{$film}-light.jpg')")
        ->assertScript("() => [...{$video}.querySelectorAll('source')].map((source) => source.src.split('/').pop()).join()", "{$film}-light.webm,{$film}-light.mp4")
        ->assertScript('() => document.querySelectorAll("[data-test^=tool-chapter-]").length', 4)
        ->assertScript('() => document.querySelector("[data-test=tool-film] figcaption").textContent.length > 40');

    $page->click('@tool-chapter-2')
        ->assertScript("() => new Promise((resolve) => { const there = () => {$video}.currentTime >= {$third} && {$video}.currentTime < {$fourth} ? resolve(true) : requestAnimationFrame(there); there(); })")
        ->assertAttribute('@tool-chapter-2', 'aria-current', 'true')
        ->assertAttribute('@tool-film-sound', 'aria-label', 'Activar sonido')
        ->click('@tool-film-sound')
        ->assertScript("() => ! {$video}.muted")
        ->click('@tool-film-toggle')
        ->assertScript("() => {$video}.paused");
})->with([
    'NOM-035' => ['nom-035', 'humetria-nom-035', 13.77, 21.47],
    'NOM-037' => ['nom-037', 'humetria-nom-037', 12.4, 19.9],
    'Ley Silla' => ['ley-silla', 'humetria-ley-silla', 14.2, 21.23],
    'complaints' => ['buzon-de-quejas', 'humetria-complaints', 13.6, 19.93],
    'compensation' => ['compensacion', 'humetria-compensation', 15.8, 21.63],
    'attendance' => ['registro-de-jornada', 'humetria-attendance', 14.33, 20.97],
    'leave' => ['vacaciones-y-permisos', 'humetria-leave', 11.8, 17.33],
    'org chart' => ['organigrama', 'humetria-org-chart', 11.67, 17.33],
    'engagement' => ['clima-laboral', 'humetria-engagement', 14.37, 21.57],
]);

it('shows the film drawn with the dark tokens in the dark theme', function (): void {
    $video = 'document.querySelector("[data-test=tool-film] video")';

    visitPage('tool', '/nom-035', dark: true)
        ->assertScript("() => {$video}.poster.endsWith('/video/humetria-nom-035.jpg')");
});

it('keeps the phone playback controls inside the video and respects reduced motion', function (): void {
    $page = resizeTo(visit('/registro-de-jornada', ['reducedMotion' => 'reduce'])->assertNoJavaScriptErrors(), 375, 812);
    $video = 'document.querySelector("[data-test=tool-film] video")';

    $page->assertScript("() => {$video}.paused && ! {$video}.autoplay")
        ->assertAttribute('@tool-film-toggle', 'aria-label', 'Reproducir video')
        ->assertScript('() => { const video = document.querySelector("[data-test=tool-film] video").getBoundingClientRect(); const control = document.querySelector("[data-test=tool-film-toggle]").getBoundingClientRect(); return control.left >= video.left && control.right <= video.right && control.top >= video.top && control.bottom <= video.bottom && control.width >= 44 && control.height >= 44; }')
        ->assertScript('() => document.documentElement.scrollWidth <= window.innerWidth');
});

it('wears the same header pill as the landing, with its sections pointing back to it', function (): void {
    visitPage('tool', '/nom-035')
        ->assertVisible('header nav[aria-label=Secciones]')
        ->assertAttribute('header a[data-test=header-how]', 'href', '/#como-funciona');
});

it('leaves the film whole and shows each step on its own real screen', function (): void {
    visitPage('tool', '/registro-de-jornada')
        ->assertScript('() => document.querySelector("#inicio img[src*=\'-mobile\']") === null')
        ->assertVisible('#como-funciona [data-test=step-stage]')
        ->click('@step-1')
        ->assertAttribute('@step-1', 'aria-pressed', 'true')
        ->assertScript('() => new Promise((resolve) => { const shown = () => document.querySelector("[data-test=step-stage] img[src=\'/images/tools/attendance-10-punch-mobile.webp\']") ? resolve(true) : requestAnimationFrame(shown); shown(); })')
        ->click('@step-2')
        ->assertScript('() => new Promise((resolve) => { const shown = () => document.querySelector("[data-test=step-stage] img[src^=\'/images/tools/attendance-03-report\']") ? resolve(true) : requestAnimationFrame(shown); shown(); })')
        ->assertSeeIn('[data-test=step-stage]', 'Paso 3');
});

it('dresses each tool page in the color of its family', function (string $path, string $family): void {
    visitPage('tool', $path)
        ->assertScript("() => document.querySelector('main').classList.contains('{$family}')");
})->with([
    'compliance' => ['/registro-de-jornada', 'family-compliance'],
    'listening' => ['/clima-laboral', 'family-listening'],
    'operations' => ['/compensacion', 'family-operations'],
]);

/**
 * Resolves once the window stopped scrolling (the jump to a section is smooth), with what the script measured there.
 */
function afterScrollSettles(string $measure): string
{
    return <<<JS
        () => new Promise((resolve) => {
            let last = -1;
            let still = 0;
            const frame = () => {
                still = window.scrollY === last ? still + 1 : 0;
                last = window.scrollY;
                still >= 12 ? resolve({$measure}) : requestAnimationFrame(frame);
            };
            requestAnimationFrame(frame);
        })
        JS;
}

it('shows the contact fields on the first screen when a phone opens the demo section', function (): void {
    $page = resizeTo(visit('/registro-de-jornada', ['reducedMotion' => 'reduce'])->assertNoJavaScriptErrors(), 375, 812);

    $page->click('a[data-track-target=sticky_demo]')
        ->assertScript(afterScrollSettles(<<<'JS'
            ['[name=name]', '[name=email]', '[name=company]', '#demo-whatsapp'].every((selector) => {
                const box = document.querySelector(`#demo-request-form ${selector}`).getBoundingClientRect();
                return box.top >= 0 && box.bottom <= window.innerHeight;
            })
            JS));
});

it('tells what comes next after the form on a phone and beside it on a wide screen', function (): void {
    $position = <<<'JS'
        (() => {
            const form = document.getElementById('demo-request-form').getBoundingClientRect();
            const steps = document.querySelector('[data-test=demo-next-steps]').getBoundingClientRect();
            return {below: steps.top > form.bottom, beside: steps.right <= form.left};
        })()
        JS;

    resizeTo(visit('/registro-de-jornada', ['reducedMotion' => 'reduce'])->assertNoJavaScriptErrors(), 375, 812)
        ->assertScript("() => { const at = {$position}; return at.below && ! at.beside; }");

    resizeTo(visit('/registro-de-jornada', ['reducedMotion' => 'reduce'])->assertNoJavaScriptErrors(), 1280, 900)
        ->assertScript("() => { const at = {$position}; return at.beside && ! at.below; }");
});

it('puts the sticky demo button away while the form is on screen and brings it back after', function (): void {
    $page = resizeTo(visit('/registro-de-jornada', ['reducedMotion' => 'reduce'])->assertNoJavaScriptErrors(), 375, 812);
    $bar = 'document.querySelector("[data-test=sticky-demo-bar]")';
    $gone = "() => new Promise((resolve) => { const check = () => getComputedStyle({$bar}).opacity === '0' && {$bar}.inert ? resolve(true) : requestAnimationFrame(check); check(); })";
    $back = "() => new Promise((resolve) => { const check = () => getComputedStyle({$bar}).opacity === '1' && ! {$bar}.inert ? resolve(true) : requestAnimationFrame(check); check(); })";

    $page->assertScript("() => getComputedStyle({$bar}).opacity === '1' && ! {$bar}.inert")
        ->click('a[data-track-target=sticky_demo]')
        ->assertScript($gone)
        ->script('() => window.scrollTo({ top: 0, behavior: "instant" })');

    $page->assertScript($back);
});
