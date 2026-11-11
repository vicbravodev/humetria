<?php

declare(strict_types=1);

/**
 * The films of the landing are served by the app so a browser can seek inside them (chapters jump to their moment):
 * seeking needs byte ranges, which neither PHP's development server nor the test server give for static files.
 */
it('serves a film whole, announcing that it takes byte ranges', function (): void {
    $response = $this->get(route('films.show', ['film' => 'humetria-leave.webm']));

    $response->assertOk()
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Type', 'video/webm');
    expect($response->headers->get('Cache-Control'))->toContain('public');
});

it('serves just the bytes asked for, so a browser can jump to a moment', function (): void {
    $size = filesize(public_path('video/humetria-leave.webm'));

    $this->get(route('films.show', ['film' => 'humetria-leave.webm']), ['Range' => 'bytes=100-199'])
        ->assertStatus(206)
        ->assertHeader('Content-Range', "bytes 100-199/{$size}")
        ->assertHeader('Content-Length', '100');
});

it('serves the mp4 of a film too', function (): void {
    $this->get(route('films.show', ['film' => 'humetria-leave.mp4']))
        ->assertOk()
        ->assertHeader('Content-Type', 'video/mp4');
});

it('does not serve anything that is not a film', function (string $film): void {
    $this->get('/media/films/'.$film)->assertNotFound();
})->with([
    'a missing film' => 'humetria-nothing.webm',
    'a poster' => 'humetria-leave.jpg',
    'a file outside the folder' => '..%2F..%2F.env',
    'a source file' => 'humetria-leave.php',
]);
