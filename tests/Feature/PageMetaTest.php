<?php

declare(strict_types=1);

use App\Http\PageSeo;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

it('hands each public page its seo, built from the configured url', function (string $url, string $component, string $route): void {
    config(['app.url' => 'https://humetria.example']);

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component)->where('seo', PageSeo::for($route)));
})->with([['/', 'landing', 'home'], ['/privacy-notice', 'privacy-notice', 'privacy-notice']]);

it('declares the canonical without the query of the visit', function (): void {
    config(['app.url' => 'https://humetria.example']);

    $this->get('/?utm_source=x&gclid=y', ['Host' => 'other.example'])
        ->assertInertia(fn (Assert $page) => $page->where('seo.canonical', 'https://humetria.example/'));
});

/*
 * The document PHP prints. SSR is off unless a test turns it on (phpunit.xml), so the tags come from the fallback.
 */

/** @return DOMXPath the head and body of a printed document */
function xpathOf(string $html): DOMXPath
{
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($dom);
}

function headValue(DOMXPath $xpath, string $query, string $attribute = 'content'): ?string
{
    $nodes = $xpath->query($query);

    return $nodes !== false && $nodes->length === 1 ? $nodes->item(0)?->getAttribute($attribute) : null;
}

it('prints the title, description, canonical, Open Graph and Twitter tags of the home', function (): void {
    config(['app.url' => 'https://humetria.example']);
    $seo = PageSeo::for('home');

    $xpath = xpathOf($this->get('/')->assertOk()->getContent());

    expect($xpath->query('//head/title')->length)->toBe(1)
        ->and($xpath->query('//head/title')->item(0)->textContent)->toBe($seo['title'].' - '.$seo['siteName'])
        ->and(headValue($xpath, '//head/meta[@name="description"]'))->toBe($seo['description'])
        ->and(headValue($xpath, '//head/link[@rel="canonical"]', 'href'))->toBe('https://humetria.example/')
        ->and(headValue($xpath, '//head/meta[@property="og:url"]'))->toBe('https://humetria.example/')
        ->and(headValue($xpath, '//head/meta[@property="og:type"]'))->toBe('website')
        ->and(headValue($xpath, '//head/meta[@property="og:locale"]'))->toBe('es_MX')
        ->and(headValue($xpath, '//head/meta[@property="og:title"]'))->toBe($seo['title'])
        ->and(headValue($xpath, '//head/meta[@property="og:image"]'))->toBe('https://humetria.example/og-v1.png')
        ->and(headValue($xpath, '//head/meta[@property="og:image:width"]'))->toBe('1200')
        ->and(headValue($xpath, '//head/meta[@property="og:image:height"]'))->toBe('630')
        ->and(headValue($xpath, '//head/meta[@property="og:image:alt"]'))->toBe($seo['image']['alt'])
        ->and(headValue($xpath, '//head/meta[@name="twitter:card"]'))->toBe('summary_large_image')
        ->and(headValue($xpath, '//head/meta[@name="twitter:title"]'))->toBe($seo['title'])
        ->and(headValue($xpath, '//head/meta[@name="twitter:image"]'))->toBe('https://humetria.example/og-v1.png');
});

it('prints one JSON-LD block, with the nonce of the policy and valid JSON', function (): void {
    $response = $this->get('/')->assertOk();
    $xpath = xpathOf($response->getContent());
    $blocks = $xpath->query('//head/script[@type="application/ld+json"]');

    expect($blocks->length)->toBe(1)
        ->and(data_get(json_decode($blocks->item(0)->textContent, true, flags: JSON_THROW_ON_ERROR), '@graph.*.@type'))->toBe(['Organization', 'WebSite'])
        ->and($blocks->item(0)->getAttribute('nonce'))->not->toBe('')
        ->and($response->headers->get('Content-Security-Policy'))->toContain("'nonce-".$blocks->item(0)->getAttribute('nonce')."'");
});

it('keeps the declared canonical when the visit carries a query or comes by another host', function (): void {
    config(['app.url' => 'https://humetria.example']);

    $xpath = xpathOf($this->get('/privacy-notice?utm_source=x', ['Host' => 'other.example'])->assertOk()->getContent());

    expect(headValue($xpath, '//head/link[@rel="canonical"]', 'href'))->toBe('https://humetria.example/privacy-notice')
        ->and(headValue($xpath, '//head/meta[@property="og:url"]'))->toBe('https://humetria.example/privacy-notice');
});

it('describes the revision of the app outside the tags the client manages', function (): void {
    config(['app.revision' => 'abc1234']);

    $xpath = xpathOf($this->get('/')->getContent());

    expect(headValue($xpath, '//head/meta[@name="app-revision"]'))->toBe('abc1234')
        ->and($xpath->query('//head/meta[@name="app-revision"][@data-inertia]')->length)->toBe(0);
});

it('has an unknown revision unless the deployment sets one', function (): void {
    expect(config('app.revision'))->toBe('unknown');
});

it('prints the same tags when the visitor is signed in and Node is not asked', function (): void {
    $xpath = xpathOf($this->actingAs(User::factory()->create())->get('/')->assertOk()->getContent());

    expect($xpath->query('//head/link[@rel="canonical"]')->length)->toBe(1)
        ->and($xpath->query('//head/meta[@name="description"]')->length)->toBe(1);
});

describe('with Node', function (): void {
    beforeEach(function (): void {
        config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
    });

    it('leaves the fallback out when Node answers, so its head is the only one', function (): void {
        Http::fake(['http://127.0.0.1:13714/render' => Http::response([
            'head' => ['<title data-inertia="">Desde Node</title>', '<meta data-inertia="description" name="description" content="Desde Node">'],
            'body' => '<div id="app"></div>',
        ])]);

        $xpath = xpathOf($this->get('/')->assertOk()->getContent());

        expect($xpath->query('//head/title')->length)->toBe(1)
            ->and($xpath->query('//head/title')->item(0)->textContent)->toBe('Desde Node')
            ->and($xpath->query('//head/meta[@name="description"]')->length)->toBe(1)
            ->and($xpath->query('//head/link[@rel="canonical"]')->length)->toBe(0)
            ->and($xpath->query('//head/script[@type="application/ld+json"]')->length)->toBe(0);
    });

    it('prints the fallback when Node fails', function (): void {
        Http::fake(['http://127.0.0.1:13714/render' => Http::response(['error' => 'boom', 'type' => 'render'], 500)]);

        $xpath = xpathOf($this->get('/')->assertOk()->getContent());

        expect($xpath->query('//head/title')->length)->toBe(1)
            ->and($xpath->query('//head/link[@rel="canonical"]')->length)->toBe(1)
            ->and(headValue($xpath, '//head/meta[@property="og:title"]'))->toBe(PageSeo::for('home')['title']);
    });

    it('prints the fallback when Node does not answer', function (): void {
        Http::fake(['http://127.0.0.1:13714/render' => fn (): never => throw new ConnectionException('refused')]);

        $xpath = xpathOf($this->get('/')->assertOk()->getContent());

        expect($xpath->query('//head/link[@rel="canonical"]')->length)->toBe(1)
            ->and($xpath->query('//head/script[@type="application/ld+json"]')->length)->toBe(1);
    });
});

it('prints no tags of a public page in the login, in an error page or in a page without seo', function (string $url): void {
    $response = $this->get($url);
    $html = $response->getContent();

    expect($html)->not->toContain('og:title')
        ->not->toContain('rel="canonical"')
        ->not->toContain('application/ld+json');
})->with(['/admin/login', '/this-page-does-not-exist']);

it('keeps the plain title in a page without seo', function (): void {
    $xpath = xpathOf($this->get('/admin/login')->getContent());

    expect($xpath->query('//head/title')->length)->toBe(1)
        ->and($xpath->query('//head/meta[@name="description"]')->length)->toBe(0);
});
it('has a share image of 1200 by 630 and the logo of the organization', function (): void {
    [$width, $height] = getimagesize(public_path('og-v1.png'));

    expect([$width, $height])->toBe([1200, 630])
        ->and(file_exists(public_path('app-icon-512.png')))->toBeTrue();
});

it('keeps in its source template the command that captures the share image', function (): void {
    $source = (string) file_get_contents(base_path('docs/design-system/og/og-v1.html'));

    expect($source)->toContain('npx playwright screenshot --viewport-size=1200,630')->toContain('public/og-v1.png');
});
