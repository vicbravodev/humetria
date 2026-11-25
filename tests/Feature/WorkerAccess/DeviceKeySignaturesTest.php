<?php

declare(strict_types=1);

use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Contracts\DeviceKeySignatures;
use App\Modules\WorkerAccess\Data\DeviceSigned;
use App\Modules\WorkerAccess\Models\WorkerDeviceKey;
use App\Modules\WorkerAccess\Support\CompanyDeviceKeySignatures;
use App\Modules\WorkerAccess\Support\OfflinePasses;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\Support\SoftDeviceKey;

mutates(CompanyDeviceKeySignatures::class);

/*
 * Attendance asks WorkerAccess whose device key signed an offline mark (plan B): the pass names the key and the person, the
 * signature is over the 32 bytes of the challenge. The answer is one for every cause (null); reading never writes.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $this->phone = new SoftDeviceKey;
    $this->key = WorkerDeviceKey::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'public_key' => $this->phone->publicKey()]);
    $this->issued = OfflinePasses::issue($this->key);
    $this->expected = hash('sha256', 'humetria-offline-mark-v1|a mark', true);
    $this->ticketAt = CarbonImmutable::parse('2026-11-24 14:00:00', 'UTC');
    $this->signatures = resolve(DeviceKeySignatures::class);
});

it('tells whose device key signed, with the dates of the pass and no revocation', function (): void {
    $signed = $this->signatures->verify($this->expected, $this->phone->sign($this->expected), $this->issued['pass'], $this->ticketAt);

    expect($signed)->toBeInstanceOf(DeviceSigned::class)
        ->deviceKeyId->toBe($this->key->id)
        ->employeeId->toBe($this->ana->id)
        ->passId->toBe($this->issued['passId'])
        ->revokedAt->toBeNull()
        ->and($signed?->passIssuedAt->toIso8601String())->toBe('2026-11-24T15:00:00+00:00')
        ->and($signed?->passExpiresAt->toIso8601String())->toBe('2026-11-25T15:00:00+00:00');
});

describe('passId', function (): void {
    it('gives the id of a pass of its own, expired or of a revoked key too: it ignores both', function (): void {
        expect($this->signatures->passId($this->issued['pass']))->toBe($this->issued['passId']);

        $this->travel(3)->days();
        $this->key->update(['revoked_at' => now(), 'revoked_reason' => 'reissued']);

        expect($this->signatures->passId($this->issued['pass']))->toBe($this->issued['passId']);
    });

    it('answers null for an altered pass, a badly formed one and one of company B read in A', function (): void {
        $other = Tenant::factory()->create();
        workIn($other);
        $foreign = WorkerDeviceKey::factory()->create(['tenant_id' => $other->id]);
        $foreignPass = OfflinePasses::issue($foreign)['pass'];
        workIn($this->tenant);
        $altered = substr($this->issued['pass'], 0, -1).($this->issued['pass'][-1] === 'A' ? 'B' : 'A');

        expect($this->signatures->passId($altered))->toBeNull()
            ->and($this->signatures->passId('v1.nope'))->toBeNull()
            ->and($this->signatures->passId(''))->toBeNull()
            ->and($this->signatures->passId($foreignPass))->toBeNull();
    });
});

describe('the revocation against the ticket', function (): void {
    it('still verifies a key revoked after the ticket was issued, whatever the reason, and says when', function (string $reason): void {
        $this->key->update(['revoked_at' => $this->ticketAt->addSecond(), 'revoked_reason' => $reason]);

        $signed = $this->signatures->verify($this->expected, $this->phone->sign($this->expected), $this->issued['pass'], $this->ticketAt);

        expect($signed)->not->toBeNull()->and($signed?->revokedAt?->equalTo($this->ticketAt->addSecond()))->toBeTrue();
    })->with(['reissued', 'not_me', 'replaced', 'left_company']);

    it('answers null for a key revoked a second before the ticket or at that very instant', function (int $seconds): void {
        $this->key->update(['revoked_at' => $this->ticketAt->addSeconds($seconds), 'revoked_reason' => 'reissued']);

        expect($this->signatures->verify($this->expected, $this->phone->sign($this->expected), $this->issued['pass'], $this->ticketAt))->toBeNull();
    })->with(['a second before' => [-1], 'at the same instant' => [0]]);
});

describe('one answer for every cause', function (): void {
    it('answers null when', function (Closure $spoil): void {
        [$expected, $signature, $pass] = $spoil($this);

        expect($this->signatures->verify($expected, $signature, $pass, $this->ticketAt))->toBeNull();
    })->with([
        'the signature is of another message' => [fn ($t) => [$t->expected, $t->phone->sign(hash('sha256', 'another', true)), $t->issued['pass']]],
        'the signature is of another key' => [fn ($t) => [$t->expected, (new SoftDeviceKey)->sign($t->expected), $t->issued['pass']]],
        'the signature is badly formed' => [fn ($t) => [$t->expected, 'not base64url!', $t->issued['pass']]],
        'the signature is short' => [fn ($t) => [$t->expected, 'AAAA', $t->issued['pass']]],
        'the signature has one bit changed' => [function ($t): array {
            $raw = Base64UrlSafe::decodeNoPadding($t->phone->sign($t->expected));
            $raw[40] = chr(ord($raw[40]) ^ 0x01);

            return [$t->expected, Base64UrlSafe::encodeUnpadded($raw), $t->issued['pass']];
        }],
        'the pass is of another key of the same person' => [function ($t): array {
            $other = WorkerDeviceKey::factory()->create(['tenant_id' => $t->tenant->id, 'employee_id' => $t->ana->id]);

            return [$t->expected, $t->phone->sign($t->expected), OfflinePasses::issue($other)['pass']];
        }],
        'the pass names the key with another person' => [function ($t): array {
            $beto = EmployeeFactory::new()->create(['tenant_id' => $t->tenant->id]);
            $lie = (new WorkerDeviceKey)->forceFill(['id' => $t->key->id, 'tenant_id' => $t->tenant->id, 'employee_id' => $beto->id]);

            return [$t->expected, $t->phone->sign($t->expected), OfflinePasses::issue($lie)['pass']];
        }],
        'the pass was altered' => [fn ($t) => [$t->expected, $t->phone->sign($t->expected), substr($t->issued['pass'], 0, -1).($t->issued['pass'][-1] === 'A' ? 'B' : 'A')]],
        'the pass names a key that does not exist' => [function ($t): array {
            $ghost = (new WorkerDeviceKey)->forceFill(['id' => '11111111-2222-4333-8444-555555555555', 'tenant_id' => $t->tenant->id, 'employee_id' => $t->ana->id]);

            return [$t->expected, $t->phone->sign($t->expected), OfflinePasses::issue($ghost)['pass']];
        }],
        'the pass is of company B read in A' => [function ($t): array {
            $other = Tenant::factory()->create();
            workIn($other);
            $foreign = WorkerDeviceKey::factory()->create(['tenant_id' => $other->id, 'public_key' => $t->phone->publicKey()]);
            $pass = OfflinePasses::issue($foreign)['pass'];
            workIn($t->tenant);

            return [$t->expected, $t->phone->sign($t->expected), $pass];
        }],
    ]);
});

it('verifies an expired pass and a person who left, and carries the expiry for Attendance to judge', function (): void {
    $this->travel(5)->days();
    DB::table('employees')->where('id', $this->ana->id)->update(['deactivated_at' => now()]);

    $signed = $this->signatures->verify($this->expected, $this->phone->sign($this->expected), $this->issued['pass'], $this->ticketAt);

    expect($signed)->not->toBeNull()->and($signed?->passExpiresAt->toIso8601String())->toBe('2026-11-25T15:00:00+00:00');
});

it('only reads: it writes neither the last use nor a log', function (): void {
    $before = $this->key->fresh()?->only(['last_used_at', 'updated_at', 'revoked_at']);
    Log::spy();

    $this->signatures->verify($this->expected, $this->phone->sign($this->expected), $this->issued['pass'], $this->ticketAt);
    $this->signatures->verify($this->expected, 'garbage', $this->issued['pass'], $this->ticketAt);

    expect($this->key->fresh()?->only(['last_used_at', 'updated_at', 'revoked_at']))->toEqual($before);
    foreach (['debug', 'info', 'notice', 'warning', 'error', 'log'] as $level) {
        Log::shouldNotHaveReceived($level);
    }
});

it('verifies the same signature twice: nothing is consumed', function (): void {
    $signature = $this->phone->sign($this->expected);

    expect($this->signatures->verify($this->expected, $signature, $this->issued['pass'], $this->ticketAt))->not->toBeNull()
        ->and($this->signatures->verify($this->expected, $signature, $this->issued['pass'], $this->ticketAt))->not->toBeNull();
});
