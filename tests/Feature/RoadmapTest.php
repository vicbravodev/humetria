<?php

declare(strict_types=1);

use App\Http\PageSeo;
use Inertia\Testing\AssertableInertia as Assert;

it('shows the public roadmap to a guest with nothing but its seo', function (): void {
    config(['app.url' => 'https://humetria.example']);

    $this->get('/roadmap')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('roadmap')
            ->has('seo')
            ->where('seo', PageSeo::for('roadmap'))
            ->where('seo.canonical', 'https://humetria.example/roadmap'));
});

it('is named roadmap and is reached with GET only', function (): void {
    expect(route('roadmap', [], false))->toBe('/roadmap');

    $this->post('/roadmap')->assertStatus(405);
});
