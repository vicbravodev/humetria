<?php

declare(strict_types=1);

use App\Http\PageSeo;

mutates(PageSeo::class);

beforeEach(function (): void {
    config(['app.url' => 'https://humetria.example']);
});

it('describes a page with its text, its canonical address and its share image', function (): void {
    $seo = PageSeo::for('home');

    expect($seo['title'])->toBe(__('seo.home.title'))
        ->and($seo['description'])->toBe(__('seo.home.description'))
        ->and($seo['canonical'])->toBe('https://humetria.example/')
        ->and($seo['siteName'])->toBe(config('app.name'))
        ->and($seo['locale'])->toBe('es_MX')
        ->and($seo['image'])->toBe([
            'url' => 'https://humetria.example/og-v1.png',
            'width' => 1200,
            'height' => 630,
            'alt' => __('seo.home.image_alt'),
        ]);
});

it('gives each indexable page its own text and path', function (string $route, string $path): void {
    $seo = PageSeo::for($route);

    expect($seo['canonical'])->toBe('https://humetria.example'.$path)
        ->and($seo['title'])->not->toBe('')
        ->and($seo['title'])->not->toStartWith('seo.')
        ->and($seo['description'])->not->toStartWith('seo.');
})->with([['home', '/'], ['privacy-notice', '/privacy-notice']]);

it('keeps the description of the home within 160 characters and naming the nine tools', function (): void {
    $description = PageSeo::for('home')['description'];

    expect(mb_strlen($description))->toBeLessThanOrEqual(160);

    foreach (['NOM-035', 'NOM-037', 'Ley Silla', 'quejas', 'Compensación', 'jornada', 'Vacaciones', 'Organigrama', 'Clima'] as $tool) {
        expect($description)->toContain($tool);
    }
});

it('builds the canonical from the configured url, whatever its trailing slash', function (string $url): void {
    config(['app.url' => $url]);

    expect(PageSeo::for('home')['canonical'])->toBe('https://humetria.example/')
        ->and(PageSeo::for('privacy-notice')['canonical'])->toBe('https://humetria.example/privacy-notice')
        ->and(PageSeo::for('home')['image']['url'])->toBe('https://humetria.example/og-v1.png');
})->with(['https://humetria.example', 'https://humetria.example/', 'https://humetria.example//']);

it('refuses a page that has no text', function (): void {
    PageSeo::for('login');
})->throws(InvalidArgumentException::class);

it('prints the structured data as a string with an Organization and a WebSite', function (): void {
    $data = json_decode(PageSeo::for('home')['jsonLd'], true, flags: JSON_THROW_ON_ERROR);

    expect(data_get($data, '@context'))->toBe('https://schema.org')
        ->and(data_get($data, '@graph.*.@type'))->toBe(['Organization', 'WebSite'])
        ->and(data_get($data, '@graph.1.inLanguage'))->toBe('es-MX')
        ->and(data_get($data, '@graph.1.url'))->toBe('https://humetria.example/')
        ->and(data_get($data, '@graph.0.logo'))->toBe('https://humetria.example/app-icon-512.png')
        ->and(data_get($data, '@graph.0.name'))->toBe(config('app.name'));
});

it('escapes the structured data so a text cannot close its script tag', function (): void {
    config(['app.name' => 'Humetría</script><script>alert(1)']);

    $json = PageSeo::for('home')['jsonLd'];

    expect(str_contains($json, '<') || str_contains($json, '>'))->toBeFalse()
        ->and(data_get(json_decode($json, true, flags: JSON_THROW_ON_ERROR), '@graph.0.name'))->toBe('Humetría</script><script>alert(1)');
});

it('leaves slashes and accents readable in the structured data', function (): void {
    $json = PageSeo::for('home')['jsonLd'];

    expect($json)->toContain('https://humetria.example/app-icon-512.png')->toContain('Humetría');
});
