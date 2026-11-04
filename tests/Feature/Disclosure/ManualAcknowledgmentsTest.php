<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Disclosure\Actions\RecordManualAcknowledgments;
use App\Modules\Disclosure\Actions\RemindPending;
use App\Modules\Disclosure\Jobs\SendPolicyReminder;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

mutates(RecordManualAcknowledgments::class, RemindPending::class);

beforeEach(function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
    $this->policy = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'published_by' => $this->hr->id, 'published_at' => '2026-11-10 15:00:00']);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $this->policy->id, 'work_center_id' => $this->plant->id]);
});

/** Asked people of the plant: `$count` of them, each with an email unless told otherwise. @return list<Employee> */
function askedPeople(int $count, ?Document $policy = null, bool $email = true): array
{
    $people = [];

    foreach (range(1, $count) as $ignored) {
        $person = Employee::factory()->create(['tenant_id' => test()->tenant->id, 'work_center_id' => test()->plant->id, 'email' => $email ? fake()->unique()->safeEmail() : null]);
        Acknowledgment::factory()->create(['tenant_id' => test()->tenant->id, 'document_id' => ($policy ?? test()->policy)->id, 'employee_id' => $person->id]);
        $people[] = $person;
    }

    return $people;
}

/** @param list<Employee> $people @return list<string> */
function idsOf(array $people): array
{
    return array_map(fn (Employee $person): string => $person->id, $people);
}

it('records the acknowledgments given on paper, in bulk, with the day and a note', function (): void {
    [$ana, $beto, $carla] = askedPeople(3);

    $count = resolve(RecordManualAcknowledgments::class)->handle($this->policy->id, [$ana->id, $beto->id], '2026-11-15', 'Hoja 3 de la plantilla', $this->hr->id);

    $recorded = Acknowledgment::query()->whereNotNull('acknowledged_at')->get();
    expect($count)->toBe(2)
        ->and($recorded->pluck('employee_id')->sort()->values()->all())->toBe(collect([$ana->id, $beto->id])->sort()->values()->all())
        ->and($recorded->first()?->channel)->toBe('manual')
        ->and($recorded->first()?->note)->toBe('Hoja 3 de la plantilla')
        ->and($recorded->first()?->recorded_by)->toBe($this->hr->id)
        ->and($recorded->first()?->acknowledged_at?->toIso8601String())->toBe('2026-11-15T06:00:00+00:00')
        ->and(Acknowledgment::query()->where('employee_id', $carla->id)->sole()->acknowledged_at)->toBeNull();
});

it('accepts the day of the publication and today, and refuses the day before and tomorrow', function (string $day, bool $accepted): void {
    [$ana] = askedPeople(1);
    $record = fn () => resolve(RecordManualAcknowledgments::class)->handle($this->policy->id, [$ana->id], $day, '', $this->hr->id);

    if ($accepted) {
        expect($record())->toBe(1);
    } else {
        expect($record)->toThrow(ValidationException::class);
        expect(Acknowledgment::query()->whereNotNull('acknowledged_at')->count())->toBe(0);
    }
})->with([
    'the day it was published' => ['2026-11-10', true],
    'today' => ['2026-11-20', true],
    'the day before it was published' => ['2026-11-09', false],
    'tomorrow' => ['2026-11-21', false],
]);

it('leaves alone who was not asked, who already confirmed, who left, who belongs to another company and who now has a newer version', function (): void {
    [$pending, $done, $left, $moved] = askedPeople(4);
    $notAsked = Employee::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $foreign = Employee::factory()->create();
    Acknowledgment::query()->where('employee_id', $done->id)->update(['channel' => 'portal', 'acknowledged_at' => '2026-11-12 10:00:00']);
    $left->update(['deactivated_at' => now()]);
    $newer = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'version' => 2, 'published_by' => $this->hr->id]);
    $otherCenter = WorkCenter::factory()->for($this->tenant)->create();
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $newer->id, 'work_center_id' => $otherCenter->id]);
    $moved->update(['work_center_id' => $otherCenter->id]);

    $count = resolve(RecordManualAcknowledgments::class)->handle($this->policy->id, idsOf([$pending, $done, $left, $moved, $notAsked, $foreign]), '2026-11-15', 'x', $this->hr->id);

    expect($count)->toBe(1)
        ->and(Acknowledgment::query()->where('channel', 'manual')->sole()->employee_id)->toBe($pending->id);
});

it('records nothing, and says so, when the version was superseded in every center', function (): void {
    [$ana] = askedPeople(1);
    $newer = Document::factory()->published()->policy()->create(['tenant_id' => $this->tenant->id, 'version' => 2, 'published_by' => $this->hr->id]);
    DocumentCenter::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $newer->id, 'work_center_id' => $this->plant->id]);

    expect(resolve(RecordManualAcknowledgments::class)->handle($this->policy->id, [$ana->id], '2026-11-15', 'x', $this->hr->id))->toBe(0);
});

it('audits the count, never the names, and logs ids only', function (): void {
    Log::spy();
    $people = askedPeople(2);

    resolve(RecordManualAcknowledgments::class)->handle($this->policy->id, idsOf($people), '2026-11-15', 'Hoja con nombres', $this->hr->id);

    $entry = AuditEntry::query()->where('event', 'disclosure.acknowledgments_recorded')->sole();
    expect($entry->metadata)->toEqual(['document_id' => $this->policy->id, 'count' => 2])
        ->and(json_encode($entry->metadata))->not->toContain('Hoja')->not->toContain($people[0]->id);
    Log::shouldHaveReceived('info')->with('disclosure.acknowledgment_recorded', ['document_id' => $this->policy->id, 'count' => 2, 'channel' => 'manual'])->once();
});

it('does not record for the version of another company', function (): void {
    $foreign = Document::factory()->published()->policy()->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(fn () => resolve(RecordManualAcknowledgments::class)->handle($foreign->id, [], '2026-11-15', '', $this->hr->id))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('reminds the pending people who have an email, once each, and nobody else', function (): void {
    Queue::fake();
    [$pending, $done] = askedPeople(2);
    $noEmail = askedPeople(1, email: false)[0];
    Acknowledgment::query()->where('employee_id', $done->id)->update(['channel' => 'portal', 'acknowledged_at' => now()]);
    [$left] = askedPeople(1);
    $left->update(['deactivated_at' => now()]);

    $count = resolve(RemindPending::class)->handle($this->policy->id);

    expect($count)->toBe(1);
    Queue::assertPushed(SendPolicyReminder::class, 1);
    Queue::assertPushed(SendPolicyReminder::class, fn (SendPolicyReminder $job): bool => $job->employeeId === $pending->id && $job->delay === 120);
    expect($noEmail->id)->not->toBe($pending->id);
});

it('logs how many it reminded', function (): void {
    Queue::fake();
    Log::spy();
    askedPeople(2);

    resolve(RemindPending::class)->handle($this->policy->id);

    Log::shouldHaveReceived('info')->with('disclosure.reminders_queued', ['document_id' => $this->policy->id, 'count' => 2])->once();
});
