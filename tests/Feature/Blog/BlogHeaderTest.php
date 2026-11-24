<?php

declare(strict_types=1);

use App\Http\PublicNav;
use Illuminate\Support\Carbon;

require_once __DIR__.'/BlogTestHelpers.php';

beforeEach(fn () => Carbon::setTestNow('2026-11-20 12:00:00'));

it('has the same links as the landing header, plain and in the same order', function (): void {
    blogFolder();

    $html = $this->get(route('blog.index'))->assertOk()->getContent();

    expect($html)->toContain('aria-label="Principal"');

    $previous = -1;
    foreach (PublicNav::links() as $link) {
        $href = $link['kind'] === 'page' ? $link['path'] : '/#'.$link['id'];
        $position = strpos((string) $html, 'data-test="'.$link['test'].'" href="'.$href.'"');

        expect($position)->not->toBeFalse()->and($position)->toBeGreaterThan($previous);
        $previous = (int) $position;
    }
});

it('keeps the footer links to the blog and the roadmap that hand weight to them', function (): void {
    blogFolder();

    $this->get(route('blog.index'))->assertSee('data-test="footer-blog"', false)->assertSee('data-test="footer-roadmap"', false);
});

it('follows the appearance the person chose in the app, not only the system', function (): void {
    blogFolder();

    $this->withUnencryptedCookie('appearance', 'dark')->get(route('blog.index'))->assertSee('<html lang="es-MX" class="dark">', false);
    $this->withUnencryptedCookie('appearance', 'light')->get(route('blog.index'))->assertDontSee('class="dark"', false);
});

it('opens the demo from the landing, since the blog has no demo form', function (): void {
    blogFolder();

    $this->get(route('blog.index'))->assertSee('data-test="header-demo" href="/#agenda-una-demo"', false);
});
