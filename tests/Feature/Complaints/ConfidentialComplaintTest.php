<?php

declare(strict_types=1);

use App\Modules\Complaints\Models\Complaint;
use App\Modules\Complaints\Models\ComplaintChannel;
use App\Modules\Complaints\Models\ComplaintHandler;
use App\Modules\Complaints\Support\TicketSigner;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    Carbon::setTestNow('2026-11-20 03:30:00');
    $this->tenant = Tenant::factory()->create();
    $owner = memberOf($this->tenant, TenantRole::Owner);
    $this->channel = ComplaintChannel::factory()->forTenant($this->tenant->id)->active()->create();
    ComplaintHandler::factory()->company($this->tenant->id)->create(['user_id' => $owner->id]);
    workIn($this->tenant);
    $this->ticket = resolve(TicketSigner::class)->issue($this->channel->access_code);
    Carbon::setTestNow('2026-11-20 03:30:10');
});

/** @param array<string, mixed> $overrides */
function confidentialForm(object $test, array $overrides = []): array
{
    return [
        'ticket' => $test->ticket,
        'type' => 'workplace_violence',
        'description' => 'Mi jefe me grita todos los días frente a mis compañeros.',
        'respondent_kind' => 'direct_supervisor',
        'identity_mode' => 'confidential',
        'reporter_name' => 'Rosa Aguilar',
        'reporter_contact' => 'Cel. 442 000 0000',
        ...$overrides,
    ];
}

function sendComplaint(object $test, array $data): Illuminate\Testing\TestResponse
{
    return $test->postJson('/c/'.$test->channel->access_code, $data);
}

it('receives a confidential complaint with the name and the contact the person gave, encrypted', function (): void {
    Log::spy();

    sendComplaint($this, confidentialForm($this))->assertCreated();

    $complaint = Complaint::query()->sole();
    expect($complaint->identity_mode->value)->toBe('confidential')
        ->and($complaint->reporter_name)->toBe('Rosa Aguilar')
        ->and($complaint->reporter_contact)->toBe('Cel. 442 000 0000');
    expect(json_encode((array) DB::table('complaints')->first()))->not->toContain('Rosa Aguilar')->not->toContain('Cel. 442');
    Log::shouldHaveReceived('info')->with('complaints.submitted', ['channel_id' => $this->channel->id, 'type' => 'workplace_violence', 'identity_mode' => 'confidential'])->once();
});

it('keeps the contact optional', function (): void {
    sendComplaint($this, confidentialForm($this, ['reporter_contact' => '']))->assertCreated();

    expect(Complaint::query()->sole()->reporter_contact)->toBeNull();
});

it('asks for the name of a confidential complaint, and takes no name for an anonymous one', function (array $override, string $field): void {
    sendComplaint($this, confidentialForm($this, $override))->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Complaint::query()->count())->toBe(0);
})->with([
    'a confidential one without a name' => [['reporter_name' => ''], 'reporter_name'],
    'a confidential one with a blank name' => [['reporter_name' => '   '], 'reporter_name'],
    'an anonymous one with a name' => [['identity_mode' => 'anonymous'], 'reporter_name'],
    'an anonymous one with a contact' => [['identity_mode' => 'anonymous', 'reporter_name' => '', 'reporter_contact' => 'a@b.mx'], 'reporter_contact'],
    'a name too long' => [['reporter_name' => str_repeat('n', 151)], 'reporter_name'],
    'a contact too long' => [['reporter_contact' => str_repeat('c', 201)], 'reporter_contact'],
]);

it('accepts a name and a contact of exactly the limit', function (): void {
    sendComplaint($this, confidentialForm($this, ['reporter_name' => str_repeat('n', 150), 'reporter_contact' => str_repeat('c', 200)]))->assertCreated();
});

/** @param array<string, mixed> $overrides */
function noticeForm(object $test, array $overrides = []): array
{
    return confidentialForm($test, [
        'type' => 'traumatic_event_notice',
        'description' => 'Presencié un accidente en el que un compañero perdió la mano en la prensa.',
        'occurred_on' => '2026-11-10',
        'involved_text' => 'Luis Pérez y el jefe de turno',
        'respondent_kind' => 'unknown',
        ...$overrides,
    ]);
}

it('receives the written notice of a traumatic event, with name, date and the people involved (6.5)', function (): void {
    sendComplaint($this, noticeForm($this))->assertCreated();

    $complaint = Complaint::query()->sole();
    expect($complaint->type->value)->toBe('traumatic_event_notice')
        ->and($complaint->reporter_name)->toBe('Rosa Aguilar')
        ->and($complaint->occurred_on?->toDateString())->toBe('2026-11-10')
        ->and($complaint->involved_text)->toBe('Luis Pérez y el jefe de turno');
    expect(json_encode((array) DB::table('complaints')->first()))->not->toContain('Luis');
});

it('does not take the notice of a traumatic event without the name, the date or as an anonymous one', function (array $override, string $field): void {
    sendComplaint($this, noticeForm($this, $override))->assertUnprocessable()->assertJsonValidationErrors($field);

    expect(Complaint::query()->count())->toBe(0);
})->with([
    'without a name' => [['reporter_name' => ''], 'reporter_name'],
    'anonymous' => [['identity_mode' => 'anonymous', 'reporter_name' => '', 'reporter_contact' => ''], 'identity_mode'],
    'without the date it happened' => [['occurred_on' => ''], 'occurred_on'],
    'with a date in the future' => [['occurred_on' => '2026-11-21'], 'occurred_on'],
    'with a category' => [['category' => 'harassment'], 'category'],
]);

it('takes the notice of a traumatic event without naming anybody involved', function (): void {
    sendComplaint($this, noticeForm($this, ['involved_text' => '']))->assertCreated();

    expect(Complaint::query()->sole()->involved_text)->toBeNull();
});

it('takes the people involved only in a notice', function (): void {
    sendComplaint($this, confidentialForm($this, ['involved_text' => 'Luis']))->assertUnprocessable()->assertJsonValidationErrors('involved_text');
});

it('refuses in the database what the form already refuses', function (array $attributes): void {
    expect(fn () => Complaint::factory()->create(['tenant_id' => $this->tenant->id, 'channel_id' => $this->channel->id, ...$attributes]))
        ->toThrow(QueryException::class);
})->with([
    'an anonymous complaint with a name' => [['identity_mode' => 'anonymous', 'reporter_name' => 'Rosa']],
    'a confidential one without a name' => [['identity_mode' => 'confidential', 'reporter_name' => null]],
    'a notice without a name' => [['type' => 'traumatic_event_notice', 'identity_mode' => 'anonymous', 'occurred_on' => '2026-11-01']],
    'a notice without the date' => [['type' => 'traumatic_event_notice', 'identity_mode' => 'confidential', 'reporter_name' => 'Rosa', 'occurred_on' => null]],
    'the people involved in a complaint that is not a notice' => [['involved_text' => 'Luis']],
]);
