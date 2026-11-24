<?php

declare(strict_types=1);

use App\Modules\Messaging\Support\PhoneForMeta;
use Tests\TestCase;

uses(TestCase::class);

mutates(PhoneForMeta::class);

// ASSUMPTION to confirm in Fase 2 (a), with a real send and a real STOP: Meta takes `52` + 10 digits for a Mexican mobile and
// the webhook may answer with `521` + 10 digits. Not verified against Meta's own documentation (only vendor summaries, which disagree).
it('sends a Mexican number as 52 and ten digits, without the plus', function (?string $stored, ?string $expected): void {
    expect((new PhoneForMeta)->toMeta($stored))->toBe($expected);
})->with([
    'null' => [null, null],
    'empty' => ['', null],
    'not a number' => ['abc', null],
    'E.164' => ['+528186875767', '528186875767'],
    'with spaces' => [' +52 81 8687 5767 ', '528186875767'],
    'the old 521 form' => ['+5218186875767', '528186875767'],
    'ten digits' => ['8186875767', '528186875767'],
    'too short' => ['+52818687', null],
    'another country' => ['+14155552671', null],
]);

it('reads a wa_id as +52 and ten digits, taking out the 1 of 521', function (string $waId, ?string $expected): void {
    expect((new PhoneForMeta)->fromWaId($waId))->toBe($expected);
})->with([
    '52 and ten digits' => ['528186875767', '+528186875767'],
    '521 and ten digits' => ['5218186875767', '+528186875767'],
    'another country, as it is' => ['14155552671', '+14155552671'],
    'empty' => ['', null],
    'not a number' => ['abc', null],
    'too short' => ['52818', null],
]);
