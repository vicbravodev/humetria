<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Actions\SubmitPersonInput;
use App\Modules\Seating\Events\PersonInputSubmitted;
use App\Modules\Seating\Models\PersonInput;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

mutates(SubmitPersonInput::class);

beforeEach(function (): void {
    $this->travelTo('2026-03-01 18:00:00');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
    $this->center = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Ana Ruiz']);
    workIn($this->tenant);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function sendInput(array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->post(route('portal.seating.inputs.store', test()->token), ['footwear' => 2, 'discomfort' => 3, 'note' => 'Me duele la espalda al final del día.', 'accepted' => '1', ...$overrides]);
}

it('stores footwear and discomfort only after the privacy notice is accepted', function (): void {
    Event::fake([PersonInputSubmitted::class]);

    sendInput()->assertRedirect(route('portal.seating.show', $this->token))->assertSessionHasNoErrors();

    $input = PersonInput::query()->sole();
    expect($input->employee_id)->toBe($this->ana->id)
        ->and($input->work_center_id)->toBe($this->center->id)
        ->and([$input->footwear, $input->discomfort, $input->note])->toBe([2, 3, 'Me duele la espalda al final del día.'])
        ->and($input->notice_accepted_at->toDateTimeString())->toBe('2026-03-01 18:00:00')
        ->and($input->submitted_at->toDateTimeString())->toBe('2026-03-01 18:00:00');
    Event::assertDispatched(PersonInputSubmitted::class, fn (PersonInputSubmitted $event): bool => $event->employeeId === $this->ana->id && $event->workCenterId === $this->center->id);
});

it('saves no health data without the acceptance, whatever else is sent', function (array $accepted): void {
    Event::fake([PersonInputSubmitted::class]);

    sendInput($accepted)->assertSessionHasErrors('accepted');

    expect(PersonInput::query()->count())->toBe(0);
    Event::assertNotDispatched(PersonInputSubmitted::class);
})->with(['absent' => [['accepted' => null]], 'refused' => [['accepted' => '0']]]);

it('rejects what the DOF tables do not allow', function (array $bad, string $field): void {
    sendInput($bad)->assertSessionHasErrors($field);

    expect(PersonInput::query()->count())->toBe(0);
})->with([
    'footwear 0' => [['footwear' => 0], 'footwear'],
    'footwear 4' => [['footwear' => 4], 'footwear'],
    'no footwear' => [['footwear' => null], 'footwear'],
    'discomfort 0' => [['discomfort' => 0], 'discomfort'],
    'discomfort 4' => [['discomfort' => 4], 'discomfort'],
    'a note too long' => [['note' => str_repeat('a', 1001)], 'note'],
]);

it('keeps every answer: a new one is a new row and the earlier ones stay', function (): void {
    sendInput(['footwear' => 1, 'discomfort' => 1, 'note' => null])->assertSessionHasNoErrors();
    $this->travelTo('2026-04-01 18:00:00');
    sendInput(['footwear' => 3, 'discomfort' => 2])->assertSessionHasNoErrors();

    expect(PersonInput::query()->orderBy('submitted_at')->pluck('footwear')->all())->toBe([1, 3]);
});

it('answers 404 for a link that is not valid and stores nothing', function (): void {
    $this->post(route('portal.seating.inputs.store', 'nope'), ['footwear' => 1, 'discomfort' => 1, 'accepted' => '1'])->assertNotFound();
    resolve(IssuePortalLink::class)->handle($this->ana->id, revoke: true);
    sendInput()->assertNotFound();

    expect(PersonInput::query()->count())->toBe(0);
});

it('never puts the note or the scores in a log line or the audit', function (): void {
    Log::spy();

    sendInput(['note' => 'Texto muy personal de salud'])->assertSessionHasNoErrors();

    Log::shouldHaveReceived('info')->with('seating.person_input_submitted', ['employee_id' => $this->ana->id, 'work_center_id' => $this->center->id])->once();
    Log::shouldNotHaveReceived('info', fn ($message, $context = []): bool => str_contains(json_encode($context), 'personal') || array_key_exists('footwear', (array) $context) || array_key_exists('discomfort', (array) $context));
    expect(json_encode(AuditEntry::query()->get()->toArray()))->not->toContain('personal')->not->toContain('footwear');
});

it('refuses in the database a score outside 1 to 3', function (): void {
    expect(fn () => DB::transaction(fn () => PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'footwear' => 4])))
        ->toThrow(QueryException::class, 'person_inputs_footwear_range')
        ->and(fn () => DB::transaction(fn () => PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id, 'discomfort' => 0])))
        ->toThrow(QueryException::class, 'person_inputs_discomfort_range');
});

it('does not read the answers of another company', function (): void {
    PersonInput::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->center->id]);
    workIn(Tenant::factory()->create());

    expect(PersonInput::query()->count())->toBe(0);
});
