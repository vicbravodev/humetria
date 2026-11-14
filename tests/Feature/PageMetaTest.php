<?php

declare(strict_types=1);

use App\Http\PageSeo;
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
