<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;

require_once __DIR__.'/../Feature/Blog/BlogTestHelpers.php';

dataset('blogLooks', [
    'light desktop' => [false, false],
    'dark desktop' => [true, false],
    'light mobile' => [false, true],
    'dark mobile' => [true, true],
]);

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 12:00:00');

    $folder = blogFolder();
    writeArticle($folder, 'jornada-40-horas', ['title' => 'title: "Jornada de 40 horas"', 'published_at' => 'published_at: 2026-11-15', 'updated_at' => 'updated_at: 2026-11-18'], extra: "## Segunda parte\n\ntexto\n\n| Año | Horas |\n| --- | --- |\n| 2026 | 48 |\n\n> «Texto de la ley.»\n\n[diagrama: jornada-escalera]\n\n[diagrama: nom035-paso-a-paso]");
    writeArticle($folder, 'otro', ['tool' => 'tool: leave', 'published_at' => 'published_at: 2026-11-14']);
});

it('renders the blog index with the newest article large and its cover', function (bool $dark, bool $mobile): void {
    visitPage('blog/index', '/blog', $dark, $mobile)
        ->assertSee('Blog de cumplimiento laboral')
        ->assertSee('Jornada de 40 horas')
        ->assertAttribute('img[src="/blog/jornada-40-horas.webp"]', 'width', '1200');
})->with('blogLooks');

it('renders an article with its table of contents, table and quote', function (bool $dark, bool $mobile): void {
    visitPage('blog/show', '/blog/jornada-40-horas', $dark, $mobile)
        ->assertSee('Jornada de 40 horas')
        ->assertSee('Segunda parte')
        ->assertSee('Texto de la ley.')
        ->assertSee('Sigue leyendo')
        ->assertAttribute('[data-test="article-cta"] a[href="/#agenda-una-demo"]', 'href', '/#agenda-una-demo');
})->with('blogLooks');

it('wears the header of the landing, with Recursos marking the blog as the page you are on', function (): void {
    visitPage('blog/index', '/blog')
        ->click('@header-resources')
        ->assertAttribute('[data-test="header-blog"]', 'aria-current', 'page')
        ->assertAttribute('[data-test="header-how"]', 'href', '/#como-funciona');
});

it('marks the blog in Recursos also on an article, not only on the index', function (): void {
    visitPage('blog/show', '/blog/jornada-40-horas')
        ->click('@header-resources')
        ->assertAttribute('[data-test="header-blog"]', 'aria-current', 'page');
});

it('opens the menu on a phone from an article, with Blog and Roadmap in it', function (): void {
    visitPage('blog/show', '/blog/jornada-40-horas', mobile: true)
        ->click('@tools-menu-mobile')
        ->assertVisible('#tools-menu a[data-test="menu-blog"]')
        ->assertVisible('#tools-menu a[data-test="menu-roadmap"]');
});

it('jumps from the table of contents to its section', function (): void {
    visitPage('blog/show', '/blog/jornada-40-horas')
        ->assertAttribute('nav[aria-label="Contenido"] a[href="#segunda-parte"]', 'href', '#segunda-parte')
        ->assertPresent('#segunda-parte');
});

it('draws the diagrams where the text asks for them, readable as text and with no axe issue', function (bool $dark, bool $mobile): void {
    visitPage('blog/show', '/blog/jornada-40-horas', $dark, $mobile)
        ->assertSee('La jornada ordinaria máxima baja cada año')
        ->assertSee('Las siete obligaciones, en orden')
        ->assertScript("() => document.querySelectorAll('article figure').length", 2)
        ->assertScript("() => document.querySelector('article figure svg[role=\"img\"]').getAttribute('aria-label')", '2026: 48 h');
})->with('blogLooks');
