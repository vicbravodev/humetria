<?php

declare(strict_types=1);

use App\Http\Consent;
use Illuminate\Http\Request;

mutates(Consent::class);

it('denies analytics unless the configured default is granted', function (?string $default, bool $allowed): void {
    config(['services.posthog.consent_default' => $default]);

    expect(Consent::analyticsAllowed(Request::create('/')))->toBe($allowed);
})->with([
    'denied' => ['denied', false],
    'granted' => ['granted', true],
    'empty' => ['', false],
    'unset' => [null, false],
    'unknown value' => ['yes', false],
    'wrong case' => ['GRANTED', false],
]);

it('is denied by default, until the owner\'s lawyer says otherwise', function (): void {
    expect(config('services.posthog.consent_default'))->toBe('denied')
        ->and(config('services.posthog.project_token'))->toBe('');
});
