<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

require_once __DIR__.'/../Feature/Blog/BlogTestHelpers.php';

/*
 * The blog with a real Node behind it (composer test:ssr): the article is in the HTML Node rendered, before any JavaScript
 * runs, so a crawler that does not run scripts reads it all, and the page hydrates without errors.
 */

beforeEach(function (): void {
    Http::allowStrayRequests(['http://127.0.0.1:13714/*']);
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => true]);
    Carbon::setTestNow('2026-11-20 12:00:00');

    $folder = blogFolder();
    writeArticle($folder, 'jornada-40-horas', ['title' => 'title: "Jornada de 40 horas"'], extra: "## Segunda parte\n\ntexto");
    writeArticle($folder, 'otro', ['tool' => 'tool: leave', 'published_at' => 'published_at: 2026-11-09']);
});

it('puts the text of the article in the HTML Node rendered, with the header of the landing', function (): void {
    $html = (string) $this->get(route('blog.show', 'jornada-40-horas'))->assertOk()->getContent();

    expect($html)->toContain('data-server-rendered')
        ->and($html)->toContain('<h1')->toContain('Jornada de 40 horas')
        ->and($html)->toContain('id="segunda-parte"')->toContain('Qué dice la ley')
        ->and($html)->toContain('data-test="header-resources"')
        ->and($html)->toContain('href="/#agenda-una-demo"');
});

it('puts the list of articles in the HTML Node rendered, each a plain link', function (): void {
    $html = (string) $this->get(route('blog.index'))->assertOk()->getContent();

    expect($html)->toContain('data-server-rendered')
        ->and($html)->toContain('href="/blog/jornada-40-horas"')->toContain('href="/blog/otro"')
        ->and($html)->toContain('src="/blog/jornada-40-horas.webp"');
});

it('hydrates the article server-rendered by Node without errors, light and dark', function (bool $dark): void {
    $pending = $dark ? visit('/blog/jornada-40-horas')->inDarkMode() : visit('/blog/jornada-40-horas')->inLightMode();

    $pending->assertNoJavaScriptErrors()
        ->assertScript('() => document.querySelector("#app").hasAttribute("data-server-rendered")')
        ->assertScript(cspViolations(), '[]')
        ->assertScript('() => document.head.querySelectorAll("link[rel=\"canonical\"]").length', 1);
})->with([false, true]);
