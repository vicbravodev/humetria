<?php

declare(strict_types=1);

use App\Modules\Notifications\Support\PushHosts;

mutates(PushHosts::class);

/*
 * The server posts to the address a phone says is its push service: only the services of the browsers are allowed, so nobody with a
 * session makes it post anywhere else.
 */
it('accepts the push services of the browsers', function (string $endpoint): void {
    expect(PushHosts::allows($endpoint))->toBeTrue();
})->with([
    'Chrome and Android' => 'https://fcm.googleapis.com/fcm/send/abc',
    'Safari and the home screen app' => 'https://web.push.apple.com/QAbc',
    'Firefox' => 'https://updates.push.services.mozilla.com/wpush/v2/abc',
    'Firefox, the bare domain' => 'https://push.services.mozilla.com/wpush/v2/abc',
    'Edge' => 'https://wns2-par02p.notify.windows.com/w/?token=abc',
    'uppercase host' => 'https://FCM.googleapis.com/fcm/send/abc',
]);

it('refuses everything else', function (string $endpoint): void {
    expect(PushHosts::allows($endpoint))->toBeFalse();
})->with([
    'no https' => 'http://fcm.googleapis.com/fcm/send/abc',
    'the host as the end of another' => 'https://fcm.googleapis.com.evil.com/x',
    'a host that only ends the same' => 'https://evilfcm.googleapis.com/x',
    'the host in the path' => 'https://evil.example/fcm.googleapis.com',
    'an address' => 'https://169.254.169.254/x',
    'localhost' => 'https://localhost/x',
    'a user in the address' => 'https://user@fcm.googleapis.com/x',
    'a user and a password' => 'https://user:pass@fcm.googleapis.com/x',
    'an explicit port' => 'https://fcm.googleapis.com:8443/x',
    'the standard port written out' => 'https://fcm.googleapis.com:443/x',
    'no host' => 'https:///x',
    'not an address' => 'not an address',
    'empty' => '',
    'a scheme with other letters' => 'ftp://fcm.googleapis.com/x',
]);
