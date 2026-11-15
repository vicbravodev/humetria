<?php

declare(strict_types=1);

it('keeps server-side rendering off under test and gives Node two seconds', function (): void {
    expect(config('inertia.ssr.enabled'))->toBeFalse()
        ->and(config('inertia.ssr.timeout'))->toBe(2);
});

it('talks to a local Node by default and checks for the bundle', function (): void {
    expect(config('inertia.ssr.url'))->toBe('http://127.0.0.1:13714')
        ->and(config('inertia.ssr.ensure_bundle_exists'))->toBeTrue()
        ->and(config('inertia.ssr.throw_on_error'))->toBeFalse();
});
