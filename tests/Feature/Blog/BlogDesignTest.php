<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/BlogTestHelpers.php';

beforeEach(fn () => Carbon::setTestNow('2026-11-20 12:00:00'));

it('gives the article a cover of a known size and a description of it', function (): void {
    writeArticle(blogFolder(), 'jornada', ['tool' => 'tool: leave']);

    $this->get(route('blog.show', 'jornada'))->assertInertia(fn (Assert $page) => $page
        ->where('article.cover', '/blog/jornada.webp')
        ->where('article.coverAlt', 'Portada del artículo sobre Vacaciones y permisos: Artículo jornada'));
});

it('shares the cover with the title on it, in Open Graph, Twitter and the JSON-LD', function (): void {
    writeArticle(blogFolder(), 'jornada');

    $response = $this->get(route('blog.show', 'jornada'));
    $html = (string) $response->getContent();
    preg_match('~<script data-inertia="json-ld"[^>]*>(.*?)</script>~s', $html, $match);

    $response->assertInertia(fn (Assert $page) => $page
        ->where('seo.image.url', 'http://localhost/blog/jornada-og.jpg')
        ->where('seo.image.alt', 'Portada del artículo sobre Registro de jornada: Artículo jornada')
        ->where('seo.image.width', 1200)->where('seo.image.height', 630));

    expect($html)->toContain('<meta data-inertia="og-image" property="og:image" content="http://localhost/blog/jornada-og.jpg">')
        ->and($html)->toContain('<meta data-inertia="twitter-image" name="twitter:image" content="http://localhost/blog/jornada-og.jpg">')
        ->and(json_decode($match[1], true)['image'])->toBe(['http://localhost/blog/jornada-og.jpg']);
});

it('keeps the generic image for the index', function (): void {
    blogFolder();

    $this->get(route('blog.index'))->assertInertia(fn (Assert $page) => $page->where('seo.image.url', 'http://localhost/og-v1.png'));
});

it('lists the h2 of the body for the table of contents, each with the id the body gives it', function (): void {
    writeArticle(blogFolder(), 'jornada', extra: "## Segunda parte\n\ntexto");

    $this->get(route('blog.show', 'jornada'))->assertInertia(fn (Assert $page) => $page
        ->has('article.headings', 2)
        ->where('article.headings.1', ['id' => 'segunda-parte', 'text' => 'Segunda parte'])
        ->where('article.html', fn (string $html) => str_contains($html, 'id="segunda-parte"')));
});

it('links the article to the page of its tool and names its family', function (): void {
    writeArticle(blogFolder(), 'jornada', ['tool' => 'tool: complaints']);

    $this->get(route('blog.show', 'jornada'))->assertInertia(fn (Assert $page) => $page
        ->where('article.toolUrl', route('tools.complaints', absolute: false))
        ->where('article.family', 'listening')
        ->where('article.toolName', 'Buzón de quejas'));
});

it('suggests up to three more articles, those of the same tool first, never the same one nor a draft', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'main-one', ['tool' => 'tool: leave', 'published_at' => 'published_at: 2026-11-10']);
    writeArticle($folder, 'same-tool', ['tool' => 'tool: leave', 'published_at' => 'published_at: 2026-11-01']);
    writeArticle($folder, 'other-new', ['published_at' => 'published_at: 2026-11-15']);
    writeArticle($folder, 'other-mid', ['published_at' => 'published_at: 2026-11-14']);
    writeArticle($folder, 'other-old', ['published_at' => 'published_at: 2026-11-13']);
    writeArticle($folder, 'draft', ['reviewed_at' => 'reviewed_at: null', 'reviewed_by' => '']);

    $this->get(route('blog.show', 'main-one'))->assertInertia(fn (Assert $page) => $page
        ->has('related', 3)
        ->where('related.0.slug', 'same-tool')
        ->where('related.1.slug', 'other-new')
        ->where('related.2.slug', 'other-mid'));
});

it('shows no suggestions when it is the only article', function (): void {
    writeArticle(blogFolder(), 'alone');

    $this->get(route('blog.show', 'alone'))->assertInertia(fn (Assert $page) => $page->has('related', 0));
});

it('gives every card of the list its cover, and its tool with the family that colors it', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'first', ['published_at' => 'published_at: 2026-11-15']);
    writeArticle($folder, 'second', ['published_at' => 'published_at: 2026-11-14', 'tool' => 'tool: engagement']);

    $this->get(route('blog.index'))->assertInertia(fn (Assert $page) => $page
        ->where('articles.0.cover', '/blog/first.webp')->where('articles.0.family', 'compliance')
        ->where('articles.1.cover', '/blog/second.webp')->where('articles.1.family', 'listening')
        ->where('articles.1.toolName', 'Clima y pulso'));
});

it('renders the page on the server for a visitor and leaves it to the browser for a signed-in one', function (): void {
    blogFolder();

    expect(App\Http\SsrPages::ALLOWED)->toMatchArray(['blog.index' => 'blog/index', 'blog.show' => 'blog/show']);
});
