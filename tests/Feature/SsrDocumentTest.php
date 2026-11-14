<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

/*
 * The document PHP prints from Node's answer: Node's head goes in <head>, its body is the page, and nothing is doubled.
 * Parsed with DOMDocument (Dom\HTMLDocument needs PHP 8.4; composer.json asks for ^8.3).
 */

beforeEach(function (): void {
    config(['inertia.ssr.enabled' => true, 'inertia.ssr.ensure_bundle_exists' => false]);
});

function documentOf(string $html): DOMXPath
{
    $dom = new DOMDocument;
    // Silenced: the HTML5 tags libxml does not know (section, nav) are not errors here.
    @$dom->loadHTML('<?xml encoding="utf-8"?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);

    return new DOMXPath($dom);
}

it('prints Node render as the body, with its head, and without a second page script', function (): void {
    $body = '<script data-page="app" type="application/json">{"component":"landing"}</script>'
        .'<div id="app" data-server-rendered="true"><h1>Contratar mejor</h1><h2>Herramientas</h2></div>';
    Http::fake(['http://127.0.0.1:13714/render' => Http::response(['head' => ['<title inertia>Humetría rendered</title>'], 'body' => $body])]);

    $xpath = documentOf($this->get('/')->assertOk()->getContent());

    expect($xpath->query('//body//div[@id="app"]//h1')->length)->toBe(1)
        ->and($xpath->query('//body//div[@id="app"]//h1')->item(0)->textContent)->toBe('Contratar mejor')
        ->and($xpath->query('//head/title')->length)->toBe(1)
        ->and($xpath->query('//head/title')->item(0)->textContent)->toBe('Humetría rendered')
        ->and($xpath->query('//script[@data-page]')->length)->toBe(1);
});

it('prints an empty app and the page script when Node gave nothing', function (): void {
    Http::fake(['http://127.0.0.1:13714/render' => Http::response(['error' => 'boom', 'type' => 'render'], 500)]);

    $xpath = documentOf($this->get('/')->assertOk()->getContent());

    expect($xpath->query('//script[@data-page]')->length)->toBe(1)
        ->and($xpath->query('//div[@id="app"]')->length)->toBe(1)
        ->and($xpath->query('//div[@id="app"]//*')->length)->toBe(0)
        ->and($xpath->query('//head/title')->length)->toBe(1);
});
