<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;

require_once __DIR__.'/BlogTestHelpers.php';

beforeEach(fn () => Carbon::setTestNow('2026-11-20 12:00:00'));

it('lists the published articles on the index, with its title and canonical', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'jornada-40-horas', ['title' => 'title: "Jornada de 40 horas"']);
    writeArticle($folder, 'draft', ['reviewed_at' => 'reviewed_at: null', 'reviewed_by' => '']);

    $this->get(route('blog.index'))
        ->assertOk()
        ->assertSee('Jornada de 40 horas')
        ->assertDontSee('Artículo draft')
        ->assertSee('href="'.route('blog.show', 'jornada-40-horas').'"', false)
        ->assertSee('<link rel="canonical" href="http://localhost/blog">', false)
        ->assertSee('<h1', false);
});

it('says there are no articles yet when there are none', function (): void {
    blogFolder();

    $this->get(route('blog.index'))->assertOk()->assertSee('Todavía no hay artículos');
});

it('paginates the index on the server', function (): void {
    $folder = blogFolder();
    config(['blog.per_page' => 2]);
    writeArticle($folder, 'one', ['published_at' => 'published_at: 2026-11-03']);
    writeArticle($folder, 'two', ['published_at' => 'published_at: 2026-11-02']);
    writeArticle($folder, 'three', ['published_at' => 'published_at: 2026-11-01']);

    $this->get(route('blog.index'))->assertSee('Artículo one')->assertSee('Artículo two')->assertDontSee('Artículo three')
        ->assertSee('rel="next"', false);
    $this->get(route('blog.index', ['page' => 2]))->assertSee('Artículo three')->assertDontSee('Artículo one')
        ->assertSee('<link rel="canonical" href="http://localhost/blog?page=2">', false)
        ->assertSee('rel="prev"', false);
});

it('shows an article with the tags of its head', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'jornada-40-horas', ['title' => 'title: "Jornada de 40 horas"', 'updated_at' => 'updated_at: 2026-11-12']);

    $this->get(route('blog.show', 'jornada-40-horas'))
        ->assertOk()
        ->assertSee('<title>Jornada de 40 horas - Humetría</title>', false)
        ->assertSee('<meta name="description" content="'.str_repeat('d', 130).'">', false)
        ->assertSee('<link rel="canonical" href="http://localhost/blog/jornada-40-horas">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<h1', false)
        ->assertSee('Qué dice la ley')
        ->assertSee('Revisado el 11 de noviembre de 2026')
        ->assertSee('3 min de lectura')
        ->assertSee('href="https://dof.gob.mx/nota_detalle.php?codigo=1"', false)
        ->assertSee('href="/#agenda-una-demo"', false);
});

it('describes the article in JSON-LD', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'jornada-40-horas', ['updated_at' => 'updated_at: 2026-11-12']);

    $html = $this->get(route('blog.show', 'jornada-40-horas'))->getContent();
    preg_match('~<script[^>]*type="application/ld\+json"[^>]*>(.*?)</script>~s', (string) $html, $match);
    $data = json_decode($match[1] ?? '', true);

    expect($data['@type'])->toBe('Article')
        ->and($data['headline'])->toBe('Artículo jornada-40-horas')
        ->and($data['datePublished'])->toBe('2026-11-10')
        ->and($data['dateModified'])->toBe('2026-11-12')
        ->and($data['inLanguage'])->toBe('es-MX')
        ->and($data['mainEntityOfPage'])->toBe('http://localhost/blog/jornada-40-horas')
        ->and($data['publisher']['@type'])->toBe('Organization');
});

it('cannot be closed early by a title that carries a script tag', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'tricky', ['title' => 'title: "Hola </script><b>x</b>"']);

    $content = (string) $this->get(route('blog.show', 'tricky'))->assertOk()->getContent();

    expect($content)->not->toContain('</script><b>')
        ->and($content)->toContain('Hola &lt;/script&gt;&lt;b&gt;x&lt;/b&gt;');
});

it('answers 404 for a draft, a future article, an unknown one and a slug that is not one', function (string $slug): void {
    $folder = blogFolder();
    writeArticle($folder, 'draft', ['reviewed_at' => 'reviewed_at: null', 'reviewed_by' => '']);
    writeArticle($folder, 'future', ['published_at' => 'published_at: 2026-11-21']);

    $this->get('/blog/'.$slug)->assertNotFound();
})->with(['draft', 'future', 'missing', 'Mayusculas', 'con_guion_bajo', '..%2F..%2F.env']);

it('may be indexed in production and is hidden elsewhere', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'live');

    $this->get(route('blog.show', 'live'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    app()->detectEnvironment(fn (): string => 'production');

    $this->get(route('blog.index'))->assertOk()->assertHeaderMissing('X-Robots-Tag');
    $this->get(route('blog.show', 'live'))->assertOk()->assertHeaderMissing('X-Robots-Tag');
    $this->get('/blog/draft')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

it('lists in its own sitemap the published articles with their last change', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'live', ['updated_at' => 'updated_at: 2026-11-12']);
    writeArticle($folder, 'draft', ['reviewed_at' => 'reviewed_at: null', 'reviewed_by' => '']);

    $response = $this->get(route('blog.sitemap'))->assertOk();
    $xml = simplexml_load_string((string) $response->getContent());

    expect((string) $response->headers->get('Content-Type'))->toContain('application/xml')
        ->and($xml)->not->toBeFalse()
        ->and(count($xml->url))->toBe(1)
        ->and((string) $xml->url[0]->loc)->toBe('http://localhost/blog/live')
        ->and((string) $xml->url[0]->lastmod)->toBe('2026-11-12');
});

it('has a robots.txt that points at the sitemap of the blog', function (): void {
    $lines = array_map('trim', file(public_path('robots.txt'), FILE_IGNORE_NEW_LINES) ?: []);

    expect($lines)->toContain('Sitemap: https://humetria.com.mx/blog/sitemap.xml');
});

it('answers 404 for a page of the index past the last one, and reads a junk page as the first', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'only');

    $this->get(route('blog.index', ['page' => 2]))->assertNotFound();
    $this->get(route('blog.index', ['page' => 'abc']))->assertOk()->assertSee('Artículo only');
    $this->get(route('blog.index', ['page' => -3]))->assertOk()->assertSee('Artículo only');
});

it('shows no review line for a draft that is only read, and no update line when nothing changed after publishing', function (): void {
    $folder = blogFolder();
    writeArticle($folder, 'plain');

    $this->get(route('blog.show', 'plain'))->assertOk()->assertDontSee('Actualizado el');
});
