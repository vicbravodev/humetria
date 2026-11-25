<?php

declare(strict_types=1);

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Support\OfflineChallenge;

mutates(OfflineChallenge::class);

/**
 * The challenge a phone signs when it marks without connection (spec «Reto canónico v1»). The phone builds the same string in
 * TypeScript; tests/Fixtures/attendance/offline-challenge-v1.json fixes it for both, and its hashes were computed outside this
 * code.
 */
dataset('canonical challenges', function (): array {
    $fixture = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/attendance/offline-challenge-v1.json'), true, flags: JSON_THROW_ON_ERROR);

    return collect($fixture['valid'])->mapWithKeys(fn (array $vector): array => [$vector['name'] => [$vector]])->all();
});

dataset('refused challenges', function (): array {
    $fixture = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/attendance/offline-challenge-v1.json'), true, flags: JSON_THROW_ON_ERROR);

    return collect($fixture['invalid'])->mapWithKeys(fn (array $vector): array => [$vector['name'] => [$vector]])->all();
});

/** @param array<string, mixed> $input */
function offlineCanonical(array $input): string
{
    return OfflineChallenge::canonical(
        $input['tenant_id'], $input['employee_id'], $input['work_center_id'], MarkType::from($input['mark_type']), $input['qr'],
        $input['declared_at'], $input['lat_e5'], $input['lng_e5'], $input['accuracy_m'], $input['agreement_version'],
        $input['seq'], $input['pass_id'], $input['ticket'],
    );
}

it('builds the canonical string of each vector and its SHA-256', function (array $vector): void {
    $canonical = offlineCanonical($vector['input']);

    expect($canonical)->toBe($vector['canonical'])
        ->and(bin2hex(OfflineChallenge::hash($canonical)))->toBe($vector['sha256_hex']);
})->with('canonical challenges');

it('gives the 32 raw bytes of the SHA-256, never its hex', function (): void {
    $hash = OfflineChallenge::hash('humetria-offline-mark-v1|x');

    expect(strlen($hash))->toBe(32)->and($hash)->toBe(hash('sha256', 'humetria-offline-mark-v1|x', true));
});

it('derives the basis from the presence of the QR, as the vectors list it', function (array $vector): void {
    expect($vector['input']['basis'])->toBe($vector['input']['qr'] === null ? 'declared_interval' : 'qr_window')
        ->and(explode('|', $vector['canonical'])[5])->toBe($vector['input']['basis']);
})->with('canonical challenges');

it('refuses a field that holds a pipe, which would shift the ones after it', function (array $vector): void {
    expect(fn () => offlineCanonical($vector['input']))->toThrow(InvalidArgumentException::class);
})->with('refused challenges');

it('leaves the three position fields empty without a position', function (): void {
    $parts = explode('|', OfflineChallenge::canonical('t', 'e', 'c', MarkType::Start, null, 100, null, null, null, 1, 1, null, 'tk'));

    expect($parts)->toBe(['humetria-offline-mark-v1', 't', 'e', 'c', 'start', 'declared_interval', '', '100', '', '', '', '1', '1', '', 'tk']);
});

it('keeps zero as a value, not as an absence', function (): void {
    $parts = explode('|', OfflineChallenge::canonical('t', 'e', 'c', MarkType::End, null, 0, 0, 0, 0, 0, 0, null, 'tk'));

    expect(array_slice($parts, 7, 6))->toBe(['0', '0', '0', '0', '0', '0']);
});

it('puts the pass id right before the ticket', function (): void {
    $parts = explode('|', OfflineChallenge::canonical('t', 'e', 'c', MarkType::Start, null, 100, null, null, null, 1, 1, 'pass-id', 'tk'));

    expect(array_slice($parts, -2))->toBe(['pass-id', 'tk']);
});

it('changes the hash with any one field', function (): void {
    $base = fn (string $tenant = 't', string $center = 'c', MarkType $type = MarkType::Start, ?string $qr = null, int $declaredAt = 100, ?int $lat = 1, int $agreement = 1, int $seq = 1, ?string $pass = null, string $ticket = 'tk'): string => bin2hex(OfflineChallenge::hash(OfflineChallenge::canonical($tenant, 'e', $center, $type, $qr, $declaredAt, $lat, 2, 3, $agreement, $seq, $pass, $ticket)));

    $hashes = [
        $base(), $base(tenant: 'u'), $base(center: 'd'), $base(type: MarkType::End), $base(qr: '1.ab'), $base(declaredAt: 101),
        $base(lat: 2), $base(lat: null), $base(agreement: 2), $base(seq: 2), $base(pass: 'p'), $base(ticket: 'tl'),
    ];

    expect(array_unique($hashes))->toHaveCount(count($hashes));
});
