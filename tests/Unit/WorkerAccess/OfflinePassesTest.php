<?php

declare(strict_types=1);

use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Support\OfflinePasses;
use Carbon\CarbonImmutable;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;

uses(TestCase::class);

mutates(OfflinePasses::class);

const PASS_TENANT = '0b9a1e5c-6f0e-4d3a-9d0e-1c2f3a4b5c6d';
const PASS_EMPLOYEE = '7c1d2e3f-4a5b-4c6d-8e7f-9a0b1c2d3e4f';
const PASS_KEY = '9e8d7c6b-5a49-4837-a625-140312ffeedd';
/** Computed apart (Python's hmac) with the key below, so the format is pinned by something other than this code. */
const PASS_FIXED_KEY = 'base64:a2tra2tra2tra2tra2tra2tra2tra2tra2tra2tra2s=';
const PASS_FIXED_PAYLOAD = 'eyJ0IjoiMGI5YTFlNWMtNmYwZS00ZDNhLTlkMGUtMWMyZjNhNGI1YzZkIiwiZSI6IjdjMWQyZTNmLTRhNWItNGM2ZC04ZTdmLTlhMGIxYzJkM2U0ZiIsImsiOiI5ZThkN2M2Yi01YTQ5LTQ4MzctYTYyNS0xNDAzMTJmZmVlZGQiLCJpIjoiNWQ0YzNiMmEtMWYwZS00ZDljLThiN2EtNmY1ZTRkM2MyYjFhIiwibiI6MTc5NTYxMTYwMCwieCI6MTc5NTY5ODAwMH0';
const PASS_FIXED = 'v1.'.PASS_FIXED_PAYLOAD.'.5hXVyI7F4o_NjN9gGUqHeast6J_z3WWcnJ3I3g_x0Mg';

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 15:00:00', 'UTC'));
    $this->key = (new WorkerDeviceKey)->forceFill(['id' => PASS_KEY, 'tenant_id' => PASS_TENANT, 'employee_id' => PASS_EMPLOYEE]);
});

/** @return array{0: string, 1: string} the payload and the HMAC of a pass, split */
function passParts(string $pass): array
{
    [, $payload, $hmac] = explode('.', $pass);

    return [$payload, $hmac];
}

it('issues a pass that reads back with its six fields and the id it was issued with', function (): void {
    $issued = OfflinePasses::issue($this->key);
    $read = OfflinePasses::read($issued['pass']);

    expect($read)->not->toBeNull()
        ->and($read['tenant'])->toBe(PASS_TENANT)
        ->and($read['employee'])->toBe(PASS_EMPLOYEE)
        ->and($read['key'])->toBe(PASS_KEY)
        ->and($read['id'])->toBe($issued['passId'])
        ->and($issued['passId'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and($read['issuedAt']->toIso8601String())->toBe('2026-11-24T15:00:00+00:00')
        ->and($read['expiresAt']->equalTo($issued['expiresAt']))->toBeTrue();
});

it('gives each pass its own id', function (): void {
    expect(OfflinePasses::issue($this->key)['passId'])->not->toBe(OfflinePasses::issue($this->key)['passId']);
});

it('is good for exactly 24 hours, and as long as worker_access.pass_hours says', function (): void {
    expect(OfflinePasses::issue($this->key)['expiresAt']->toIso8601String())->toBe('2026-11-25T15:00:00+00:00');

    config(['worker_access.pass_hours' => 6]);

    expect(OfflinePasses::issue($this->key)['expiresAt']->toIso8601String())->toBe('2026-11-24T21:00:00+00:00');
});

it('has the format v1.{payload}.{hmac} of about 318 characters, signed with the app key under its own domain', function (): void {
    config(['app.key' => PASS_FIXED_KEY]);
    $pass = OfflinePasses::issue($this->key)['pass'];
    [$payload, $hmac] = passParts($pass);

    expect(str_starts_with($pass, 'v1.'))->toBeTrue()
        ->and(strlen($pass))->toBe(318)
        ->and(json_decode(Base64UrlSafe::decodeNoPadding($payload), true))->toHaveKeys(['t', 'e', 'k', 'i', 'n', 'x'])
        ->and($hmac)->toBe(Base64UrlSafe::encodeUnpadded(hash_hmac('sha256', "worker-access-offline-pass|v1.{$payload}", PASS_FIXED_KEY, true)));
});

it('reads a fixed pass, which pins the format', function (): void {
    config(['app.key' => PASS_FIXED_KEY, 'app.previous_keys' => []]);
    $read = OfflinePasses::read(PASS_FIXED);

    expect($read)->not->toBeNull()
        ->and($read['tenant'])->toBe(PASS_TENANT)
        ->and($read['employee'])->toBe(PASS_EMPLOYEE)
        ->and($read['key'])->toBe(PASS_KEY)
        ->and($read['id'])->toBe('5d4c3b2a-1f0e-4d9c-8b7a-6f5e4d3c2b1a')
        ->and($read['issuedAt']->getTimestamp())->toBe(1795611600)
        ->and($read['expiresAt']->getTimestamp())->toBe(1795698000);
});

it('does not compare with now: an expired pass still reads, and the validity is for Attendance to decide', function (): void {
    $issued = OfflinePasses::issue($this->key);
    $this->travel(30)->days();

    expect(OfflinePasses::read($issued['pass']))->not->toBeNull();
});

it('answers null for a pass altered in its payload or its HMAC, of another version, short or with a field missing', function (string $case): void {
    $pass = OfflinePasses::issue($this->key)['pass'];
    [$payload, $hmac] = passParts($pass);
    $resign = fn (string $body): string => "v1.{$body}.".Base64UrlSafe::encodeUnpadded(hash_hmac('sha256', "worker-access-offline-pass|v1.{$body}", config()->string('app.key'), true));
    $without = fn (string $field): string => $resign(Base64UrlSafe::encodeUnpadded((string) json_encode(array_diff_key(json_decode(Base64UrlSafe::decodeNoPadding($payload), true), [$field => 1]))));

    $bad = match ($case) {
        'payload byte' => 'v1.'.($payload[0] === 'A' ? 'B' : 'A').substr($payload, 1).'.'.$hmac,
        'hmac byte' => "v1.{$payload}.".($hmac[0] === 'A' ? 'B' : 'A').substr($hmac, 1),
        'v2' => "v2.{$payload}.{$hmac}",
        'two parts' => "v1.{$payload}",
        'four parts' => "{$pass}.x",
        'no key id' => $without('k'),
        'no pass id' => $without('i'),
        'no expiry' => $without('x'),
        'a number as the id' => $resign(Base64UrlSafe::encodeUnpadded((string) json_encode(['t' => PASS_TENANT, 'e' => PASS_EMPLOYEE, 'k' => PASS_KEY, 'i' => 7, 'n' => 1, 'x' => 2]))),
        'a text as the date' => $resign(Base64UrlSafe::encodeUnpadded((string) json_encode(['t' => PASS_TENANT, 'e' => PASS_EMPLOYEE, 'k' => PASS_KEY, 'i' => 'x', 'n' => 'late', 'x' => 2]))),
        'not json' => $resign(Base64UrlSafe::encodeUnpadded('not json')),
        'a list' => $resign(Base64UrlSafe::encodeUnpadded('[1,2,3]')),
        'empty' => '',
    };

    expect(OfflinePasses::read($bad))->toBeNull();
})->with(['payload byte', 'hmac byte', 'v2', 'two parts', 'four parts', 'no key id', 'no pass id', 'no expiry', 'a number as the id', 'a text as the date', 'not json', 'a list', 'empty']);

/** A pass of `$length` characters with a right HMAC: a padding field in the payload, so only the length can refuse it. */
function paddedPass(int $length): string
{
    for ($pad = 0; $pad < 400; $pad++) {
        $payload = Base64UrlSafe::encodeUnpadded((string) json_encode(['t' => PASS_TENANT, 'e' => PASS_EMPLOYEE, 'k' => PASS_KEY, 'i' => PASS_KEY, 'n' => 1, 'x' => 2, 'p' => str_repeat('a', $pad)]));
        $pass = "v1.{$payload}.".Base64UrlSafe::encodeUnpadded(hash_hmac('sha256', "worker-access-offline-pass|v1.{$payload}", config()->string('app.key'), true));

        if (strlen($pass) === $length) {
            return $pass;
        }
    }

    throw new LogicException('No pass of that length.');
}

it('refuses a pass of 513 characters even when it is signed, and reads one of 511', function (): void {
    // A base64url payload never makes a pass of exactly 512, so the edge is tested from both sides.
    expect(OfflinePasses::read(paddedPass(511)))->not->toBeNull()
        ->and(OfflinePasses::read(paddedPass(513)))->toBeNull();
});

it('reads a pass signed with the old app key after a rotation if it is in app.previous_keys, and not once it is gone', function (): void {
    $old = 'base64:'.base64_encode(str_repeat('o', 32));
    config(['app.key' => $old]);
    $pass = OfflinePasses::issue($this->key)['pass'];

    config(['app.key' => 'base64:'.base64_encode(str_repeat('n', 32)), 'app.previous_keys' => [$old]]);
    expect(OfflinePasses::read($pass))->not->toBeNull();

    config(['app.previous_keys' => []]);
    expect(OfflinePasses::read($pass))->toBeNull();
});

it('signs with the current key, never an old one', function (): void {
    $old = 'base64:'.base64_encode(str_repeat('o', 32));
    config(['app.previous_keys' => [$old]]);
    [$payload, $hmac] = passParts(OfflinePasses::issue($this->key)['pass']);

    expect($hmac)->toBe(Base64UrlSafe::encodeUnpadded(hash_hmac('sha256', "worker-access-offline-pass|v1.{$payload}", config()->string('app.key'), true)));
});
