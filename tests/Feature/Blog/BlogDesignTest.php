<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;

require_once __DIR__.'/BlogTestHelpers.php';

beforeEach(fn () => Carbon::setTestNow('2026-11-20 12:00:00'));

it('shows the cover of the article with a fixed size, a description and priority, since it is the first thing painted', function (): void {
    writeArticle(blogFolder(), 'jornada', ['tool' => 'tool: leave']);

    $this->get(route('blog.show', 'jornada'))
        ->assertSee('src="/blog/jornada.webp"', false)
        ->assertSee('width="1200" height="630"', false)
        ->assertSee('alt="Portada del artículo sobre Vacaciones y permisos: Artículo jornada"', false)
        ->assertSee('fetchpriority="high"', false)
        ->assertDontSee('loading="lazy"', false);
});

it('shares the cover with the title on it, in Open Graph, Twitter and the JSON-LD', function (): void {
    writeArticle(blogFolder(), 'jornada');

    $html = (string) $this->get(route('blog.show', 'jornada'))->getContent();
    preg_match('~type="application/ld\+json"[^>]*>(.*?)</script>~s', $html, $match);

    expect($html)->toContain('<meta property="og:image" content="http://localhost/blog/jornada-og.jpg">')
        ->and($html)->toContain('<meta name="twitter:image" content="http://localhost/blog/jornada-og.jpg">')
        ->and($html)->toContain('<meta property="og:image:alt" content="Portada del artículo sobre Registro de jornada: Artículo jornada">')
        ->and(json_decode($match[1], true)['image'])->toBe(['http://localhost/blog/jornada-og.jpg']);
});

it('keeps the generic image for the index', function (): void {
    blogFolder();

    $this->get(route('blog.index'))->assertSee('<meta property="og:image" content="http://localhost/og-v1.png">', false);
});

it('offers a table of contents that links to each h2', function (): void {
    writeArticle(blogFolder(), 'jornada', extra: "## Segunda parte\n\ntexto");

    $this->get(route('blog.show', 'jornada'))
        ->assertSee('aria-label="Contenido"', false)
        ->assertSee('href="#segunda-parte"', false)
        ->assertSee('href="#que-dice-la-ley"', false)
        ->assertSee('id="que-dice-la-ley"', false);
});

it('links the article to the page of its tool and dresses it in its family', function (): void {
    writeArticle(blogFolder(), 'jornada', ['tool' => 'tool: complaints']);

    $this->get(route('blog.show', 'jornada'))
        ->assertSee('href="'.route('tools.complaints').'"', false)
        ->assertSee('family-listening', false);
});

it('suggests up to three more articles, those of the same tool first, never the same one nor a draft', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'main-one', ['tool' => 'tool: leave', 'published_at' => 'published_at: 2026-11-10']);
    writeArticle($folder, 'same-tool', ['tool' => 'tool: leave', 'published_at' => 'published_at: 2026-11-01']);
    writeArticle($folder, 'other-new', ['published_at' => 'published_at: 2026-11-15']);
    writeArticle($folder, 'other-mid', ['published_at' => 'published_at: 2026-11-14']);
    writeArticle($folder, 'other-old', ['published_at' => 'published_at: 2026-11-13']);
    writeArticle($folder, 'draft', ['reviewed_at' => 'reviewed_at: null', 'reviewed_by' => '']);

    $html = (string) $this->get(route('blog.show', 'main-one'))->getContent();
    $related = substr($html, (int) strpos($html, 'aria-labelledby="related"'));

    expect(substr_count($related, '<h3'))->toBe(3)
        ->and(strpos($related, 'Artículo same-tool'))->toBeLessThan((int) strpos($related, 'Artículo other-new'))
        ->and($related)->toContain('Artículo other-new')->toContain('Artículo other-mid')
        ->not->toContain('Artículo other-old')->not->toContain('Artículo draft')->not->toContain('Artículo main-one');
});

it('skips the table of contents when there is nothing to jump to', function (): void {
    writeArticle(blogFolder(), 'jornada');

    $this->get(route('blog.show', 'jornada'))->assertDontSee('aria-label="Contenido"', false);
});

it('shows no suggestions when it is the only article', function (): void {
    writeArticle(blogFolder(), 'alone');

    $this->get(route('blog.show', 'alone'))->assertDontSee('aria-labelledby="related"', false);
});

it('closes with the demo outside the article, so the page has no complementary inside its main content', function (): void {
    writeArticle(blogFolder(), 'jornada');

    $html = (string) $this->get(route('blog.show', 'jornada'))->getContent();

    expect(strpos($html, '</article>'))->toBeLessThan((int) strpos($html, 'data-test="article-cta"'));
});

it('shows each card of the index with its cover, size included, and lazy loads all but the first', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'first', ['published_at' => 'published_at: 2026-11-15']);
    writeArticle($folder, 'second', ['published_at' => 'published_at: 2026-11-14']);

    $html = (string) $this->get(route('blog.index'))->getContent();

    expect($html)->toContain('src="/blog/first.webp"')->toContain('src="/blog/second.webp"')
        ->and(substr_count($html, 'width="1200" height="630"'))->toBe(2)
        ->and(substr_count($html, 'loading="lazy"'))->toBe(1)
        ->and(substr_count($html, 'fetchpriority="high"'))->toBe(1);
});

it('marks the blog as the current page of the header', function (): void {
    blogFolder();

    $this->get(route('blog.index'))->assertSee('data-test="header-blog" href="/blog"', false)->assertSee('aria-current="page"', false);
});
