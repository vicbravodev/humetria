<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;

it('never sends a preload Link header, however many chunks a page has', function (): void {
    $assets = [];
    foreach (range(1, 80) as $i) {
        $assets["https://humetria.test/build/assets/chunk-{$i}-abcdef12.js"] = ['rel="modulepreload"', 'as="script"'];
    }
    Vite::partialMock()->shouldReceive('preloadedAssets')->andReturn($assets);

    $response = $this->get(route('login'));

    $response->assertHeaderMissing('Link');
    expect(strlen($response->headers->__toString()))->toBeLessThan(4096);
});
