<?php

declare(strict_types=1);

use App\Modules\Attendance\Data\OfflineOutcome;
use App\Modules\Attendance\Enums\OfflineRejection;
use App\Modules\Attendance\Http\Requests\OfflinePunchRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Validator;

/*
 * tests/Fixtures/attendance/offline-delivery-v1.json is the contract of the delivery both ends read: this side proves that its
 * request passes the envelope and that the server produces exactly its answers; the phone's tests read the same file to prove
 * its rule for deleting a mark and for splitting a batch.
 */
beforeEach(function (): void {
    $this->fixture = json_decode((string) file_get_contents(__DIR__.'/../../Fixtures/attendance/offline-delivery-v1.json'), true, flags: JSON_THROW_ON_ERROR);
});

it('has a request that passes the envelope: both signers, the position as integers and a ticket that fits', function (): void {
    $validator = Validator::make($this->fixture['request'], (new OfflinePunchRequest)->rules());

    expect($validator->errors()->all())->toBe([])
        ->and(array_column($this->fixture['request']['items'], 'signer'))->toBe(['passkey', 'device_key'])
        ->and($this->fixture['request']['items'][0])->toMatchArray(['lat_e5' => 1943271, 'lng_e5' => -9913320, 'accuracy_m' => 12]);
});

it('has the answers the server gives, exactly, one of each case', function (): void {
    $outcomes = [
        OfflineOutcome::received(0, 'e'),
        OfflineOutcome::duplicate(1, 'received', null, 'e'),
        OfflineOutcome::duplicate(2, 'received', OfflineOutcome::ALREADY_RECORDED, 'e'),
        OfflineOutcome::duplicate(3, 'rejected', OfflineRejection::SequenceConflict->value, 'e'),
        OfflineOutcome::rejected(4, OfflineRejection::WrongCenter, 'e'),
        OfflineOutcome::rejected(5, OfflineOutcome::TICKET_INVALID),
        OfflineOutcome::rejected(6, OfflineOutcome::SIGNATURE_INVALID),
        OfflineOutcome::conflict(7),
    ];

    expect(array_map(fn (OfflineOutcome $outcome): array => $outcome->toArray(), $outcomes))->toBe($this->fixture['response']['results']);
});

it('has the envelope of the errors the route answers', function (): void {
    Tenant::factory()->create(['slug' => 'acme']);

    $refused = $this->postJson(route('attendance.device.punch.offline', 'acme'), ['items' => []])->assertUnprocessable();
    $envelope = array_column($this->fixture['envelope'], 'body', 'status');

    expect(array_keys($envelope))->toBe([413, 422, 403, 404, 429])
        ->and(array_keys($refused->json()))->toBe(array_keys($envelope[422]))
        ->and(array_keys($envelope[422]['errors']))->toBe(['items']);
});
