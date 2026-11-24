<?php

declare(strict_types=1);

use App\Modules\Attendance\Support\CurrentAgreement;
use App\Modules\Attendance\Support\OfflineTicket;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;

mutates(OfflineTicket::class);

/*
 * The ticket a phone keeps to mark without a connection (criterio 3.3.1): signed by the server, with the company, the
 * person, when it was issued and a random value. On its own it proves nothing: it only bounds the time from below.
 */
beforeEach(function (): void {
    config(['fortify.passkeys.relying_party_id' => 'localhost']);
    $this->travelTo(CarbonImmutable::parse('2026-11-09 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->tickets = resolve(OfflineTicket::class);
});

it('issues a batch of eight, each of 235 characters and with its own value, that verifies for the person', function (): void {
    $batch = $this->tickets->batch($this->ana->id);
    $nonces = [];

    expect($batch)->toHaveCount(8);

    foreach ($batch as $entry) {
        $verified = $this->tickets->verify($entry['ticket']);

        expect(strlen($entry['ticket']))->toBe(235)->and($entry['ticket'])->toStartWith('v1.')
            ->and($entry['issuedAt'])->toBe(CarbonImmutable::now()->getTimestamp())
            ->and($verified)->not->toBeNull()
            ->and($verified['employee_id'] ?? null)->toBe($this->ana->id)
            ->and($verified['issued_at']->getTimestamp() ?? null)->toBe($entry['issuedAt'])
            ->and($verified['nonce'] ?? null)->toMatch('/^[0-9a-f]{32}$/');
        $nonces[] = $verified['nonce'] ?? null;
    }

    expect(array_unique($nonces))->toHaveCount(8);
});

it('takes the size of the batch from the configuration', function (): void {
    config(['attendance.offline.ticket_batch' => 3]);

    expect($this->tickets->batch($this->ana->id))->toHaveCount(3);
});

it('rounds the instant it was issued up to the second, so it is never dated before the mark that issued it', function (string $now, string $issued): void {
    $this->travelTo(CarbonImmutable::parse($now, 'UTC'));

    $entry = $this->tickets->batch($this->ana->id)[0];

    expect($entry['issuedAt'])->toBe(CarbonImmutable::parse($issued, 'UTC')->getTimestamp())
        ->and($this->tickets->verify($entry['ticket'])['issued_at'] ?? null)->toEqual(CarbonImmutable::parse($issued, 'UTC'));
})->with([
    'a fraction of a second' => ['2026-11-09 08:00:00.400', '2026-11-09 08:00:01'],
    'almost the next second' => ['2026-11-09 08:00:00.999999', '2026-11-09 08:00:01'],
    'the exact second' => ['2026-11-09 08:00:00', '2026-11-09 08:00:00'],
]);

it('does not verify the ticket of another company', function (): void {
    $ticket = $this->tickets->batch($this->ana->id)[0]['ticket'];
    workIn(Tenant::factory()->create());

    expect(resolve(OfflineTicket::class)->verify($ticket))->toBeNull();
});

it('does not verify a ticket that was altered or is not of this format', function (string $kind): void {
    $ticket = $this->tickets->batch($this->ana->id)[0]['ticket'];
    [$version, $payload, $hmac] = explode('.', $ticket);
    $swapped = $this->tickets->batch($this->ana->id)[1]['ticket'];

    $altered = match ($kind) {
        'a changed signature' => implode('.', [$version, $payload, substr($hmac, 0, -1).($hmac[-1] === 'A' ? 'B' : 'A')]),
        'the signature of another ticket' => implode('.', [$version, $payload, explode('.', $swapped)[2]]),
        'a changed payload' => implode('.', [$version, substr($payload, 0, -2).($payload[-2] === 'A' ? 'B' : 'A').$payload[-1], $hmac]),
        'another version' => implode('.', ['v2', $payload, $hmac]),
        'no signature' => "{$version}.{$payload}",
        'nothing' => '',
        'an extra part' => "{$ticket}.x",
        'a payload that is not base64' => implode('.', [$version, '***', $hmac]),
    };

    expect($this->tickets->verify($altered))->toBeNull();
})->with(['a changed signature', 'the signature of another ticket', 'a changed payload', 'another version', 'no signature', 'nothing', 'an extra part', 'a payload that is not base64']);

it('keeps verifying the tickets of the previous app key, and stops when it is dropped', function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'app.previous_keys' => []]);
    $old = resolve(OfflineTicket::class)->batch($this->ana->id)[0]['ticket'];

    config(['app.key' => 'base64:'.base64_encode(str_repeat('b', 32)), 'app.previous_keys' => ['base64:'.base64_encode(str_repeat('a', 32))]]);
    $new = resolve(OfflineTicket::class)->batch($this->ana->id)[0]['ticket'];

    expect(resolve(OfflineTicket::class)->verify($old))->not->toBeNull()
        ->and(resolve(OfflineTicket::class)->verify($new))->not->toBeNull();

    config(['app.previous_keys' => []]);

    expect(resolve(OfflineTicket::class)->verify($old))->toBeNull()
        // A new ticket is signed with the current key, not the previous one.
        ->and(resolve(OfflineTicket::class)->verify($new))->not->toBeNull();
});

it('tells the phone who it is, where it works, which version of the agreement it accepted, who it signs for and whether offline is on', function (): void {
    $person = resolve(Roster::class)->find($this->ana->id) ?? throw new LogicException('The person is missing.');

    $without = $this->tickets->context($person);
    agreementAccepted($this->ana->id);
    $accepted = $this->tickets->context($person);
    config(['attendance.offline.enabled' => false]);
    $off = $this->tickets->context($person);

    expect($without)->toBe(['tenantId' => $this->tenant->id, 'employeeId' => $this->ana->id, 'centerId' => $this->plant->id, 'agreementVersion' => null, 'rpId' => 'localhost', 'offline' => true])
        ->and($accepted['agreementVersion'])->toBe(resolve(CurrentAgreement::class)->get()->version)
        ->and($off['offline'])->toBeFalse();
});
