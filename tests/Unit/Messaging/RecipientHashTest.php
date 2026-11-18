<?php

declare(strict_types=1);

use App\Modules\Messaging\Support\RecipientHash;
use Tests\TestCase;

uses(TestCase::class);

mutates(RecipientHash::class);

beforeEach(function (): void {
    config(['messaging.recipient_keys' => [1 => base64_encode('first-key'), 2 => base64_encode('second-key')]]);
});

it('gives the same hash for the same phone and key', function (): void {
    expect((new RecipientHash)->current('+528186875767'))->toBe((new RecipientHash)->current('+528186875767'));
});

it('hashes with the newest key and says its version', function (): void {
    $current = (new RecipientHash)->current('+528186875767');

    expect($current['version'])->toBe(2)
        ->and($current['hash'])->toBe(hash_hmac('sha256', '+528186875767', 'second-key'))
        ->and($current['hash'])->toHaveLength(64);
});

it('gives a hash per key of the list, all different, so a STOP finds rows of any version', function (): void {
    $all = (new RecipientHash)->all('+528186875767');

    expect($all)->toHaveCount(2)
        ->and($all[0])->not->toBe($all[1])
        ->and($all)->toContain(hash_hmac('sha256', '+528186875767', 'first-key'), hash_hmac('sha256', '+528186875767', 'second-key'));
});

it('never gives the phone back in the hash', function (): void {
    expect((new RecipientHash)->current('+528186875767')['hash'])->not->toContain('8186875767');
});

it('refuses to hash without a key', function (): void {
    config(['messaging.recipient_keys' => []]);

    (new RecipientHash)->current('+528186875767');
})->throws(LogicException::class);
