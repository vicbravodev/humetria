<?php

declare(strict_types=1);

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Client;

/*
 * The time clock's browser tests share these: a phone's fingerprint reader and its position. WebAuthn needs a domain, not
 * an address (the browser refuses a relying party on 127.0.0.1), so these tests open the app on localhost. Pest Browser
 * does not expose Playwright's CDP session nor the context's geolocation once the page is open, so both are reached with
 * Playwright's own protocol messages on the page Pest drives.
 */

/** A private property of Pest Browser's objects: the page it drives and the Playwright ids behind it. */
function pestInternal(object $object, string $property): mixed
{
    return (new ReflectionProperty($object, $property))->getValue($object);
}

/** The Playwright id of the browser context of `$page`. */
function contextGuid(AwaitableWebpage $page): string
{
    $guid = pestInternal(pestInternal($page, 'page')->context(), 'guid');

    return is_string($guid) ? $guid : throw new LogicException('The page has no context.');
}

/**
 * Makes the page's origin the relying party, and gives the page a platform authenticator with a resident key and
 * user verification that always succeeds (the person touches the reader).
 */
function withFingerprintReader(AwaitableWebpage $page, bool $verifies = true): void
{
    $origin = parse_url($page->url(), PHP_URL_SCHEME).'://'.parse_url($page->url(), PHP_URL_HOST).':'.parse_url($page->url(), PHP_URL_PORT);
    config(['fortify.passkeys.relying_party_id' => 'localhost', 'fortify.passkeys.allowed_origins' => [$origin]]);

    $playwrightPage = pestInternal($page, 'page');
    $session = null;

    foreach (Client::instance()->execute(contextGuid($page), 'newCDPSession', ['page' => ['guid' => pestInternal($playwrightPage, 'guid')]]) as $message) {
        $session = $message['result']['session']['guid'] ?? $session;
    }

    // Without a session the authenticator would be missing and the test would fail for the wrong reason.
    expect($session)->toBeString()->not->toBeEmpty();
    $send = fn (string $method, array $params): array => iterator_to_array(Client::instance()->execute((string) $session, 'send', ['method' => $method, 'params' => $params]));
    $send('WebAuthn.enable', ['enableUI' => false]);
    $send('WebAuthn.addVirtualAuthenticator', ['options' => [
        'protocol' => 'ctap2',
        'transport' => 'internal',
        'hasResidentKey' => true,
        'hasUserVerification' => true,
        'isUserVerified' => $verifies,
        'automaticPresenceSimulation' => true,
    ]]);
}

/** The phone is at `$latitude`, `$longitude` (with `$accuracy` meters), and the person lets the page read it. */
function standingAt(AwaitableWebpage $page, float $latitude, float $longitude, float $accuracy = 10.0): void
{
    iterator_to_array(Client::instance()->execute(contextGuid($page), 'grantPermissions', ['permissions' => ['geolocation']]));
    iterator_to_array(Client::instance()->execute(contextGuid($page), 'setGeolocation', ['geolocation' => ['latitude' => $latitude, 'longitude' => $longitude, 'accuracy' => $accuracy]]));
}
