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
        ->assertScript('() => document.querySelectorAll("#otras-herramientas a").length', 8);
})->with(fn (): array => array_map(fn (string $key): array => [$key, ToolPages::SLUGS[$key]], array_combine(array_keys(ToolPages::SLUGS), array_keys(ToolPages::SLUGS))));

it('looks right in light, dark and on a phone', function (bool $dark, bool $mobile): void {
    visitPage('tool', '/registro-de-jornada', $dark, $mobile)
        ->assertSee('Qué pide la ley')
        ->assertSee('Cómo lo resuelve Humetría')
        ->assertSee('Para quién es')
        ->assertSee('Preguntas frecuentes');
})->with($looks);

it('opens an answer when its question is clicked', function (): void {
    $faq = ToolPages::page('attendance')['faqs'][0];

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
        ->click('Para mi empresa o grupo de empresas')
        ->click('51–100')
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
