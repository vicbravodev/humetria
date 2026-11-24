<?php

declare(strict_types=1);

use App\Modules\Disclosure\Actions\RequestAcknowledgments;
use App\Modules\Disclosure\Jobs\SendPolicyReminder;
use App\Modules\Disclosure\Listeners\RequestOnPublication;
use App\Modules\Disclosure\Listeners\RequestPolicyFromNewStaff;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Organization\Events\EmployeeCreated;
use App\Modules\Organization\Events\EmployeeMoved;
use App\Modules\Organization\Events\EmployeeReactivated;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

mutates(RequestAcknowledgments::class, RequestOnPublication::class, RequestPolicyFromNewStaff::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create();
    $this->south = WorkCenter::factory()->for($this->tenant)->create();
    workIn($this->tenant);
});

/** A published policy that reached `$centers` (version `$version`). @param list<WorkCenter> $centers */
function policyReaching(array $centers, int $version = 1): Document
{
    $policy = Document::factory()->published()->policy()->create(['tenant_id' => test()->tenant->id, 'version' => $version, 'published_by' => test()->hr->id]);

    foreach ($centers as $center) {
        DocumentCenter::factory()->create(['tenant_id' => test()->tenant->id, 'document_id' => $policy->id, 'work_center_id' => $center->id]);
    }

    return $policy;
}

/** @return list<string> the employees asked for a version, sorted */
function askedFor(Document $policy): array
{
    $ids = Acknowledgment::query()->where('document_id', $policy->id)->pluck('employee_id')->all();
    sort($ids);

    return $ids;
}

it('asks the active people of the centers the version reached, and nobody else', function (): void {
    $northPeople = staffOf($this->tenant, $this->north, 3);
    $southPeople = staffOf($this->tenant, $this->south, 2);
    Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'area_id' => $northPeople[0]->area_id]);
    $stranger = Employee::factory()->create();
    $policy = policyReaching([$this->north]);

    $made = resolve(RequestAcknowledgments::class)->handle($policy->id);

    $expected = $northPeople->pluck('id')->sort()->values()->all();
    expect($made)->toBe(3)
        ->and(askedFor($policy))->toBe($expected)
        ->and(Acknowledgment::query()->whereNotNull('acknowledged_at')->count())->toBe(0)
        ->and(askedFor($policy))->not->toContain($stranger->id, $southPeople[0]->id);
});

it('stamps each request with the day it was made, unconfirmed', function (): void {
    $this->travelTo('2026-11-20 18:00:00');
    staffOf($this->tenant, $this->north, 1);
    $policy = policyReaching([$this->north]);

    resolve(RequestAcknowledgments::class)->handle($policy->id);
    $ack = Acknowledgment::query()->sole();

    expect($ack->requested_at->toIso8601String())->toBe('2026-11-20T18:00:00+00:00')
        ->and($ack->acknowledged_at)->toBeNull()
        ->and($ack->channel)->toBeNull();
});

it('asks everyone at the edges of a chunk of 500: 499, 500 and 501 people', function (int $people): void {
    staffOf($this->tenant, $this->north, $people);
    $policy = policyReaching([$this->north]);

    expect(resolve(RequestAcknowledgments::class)->handle($policy->id))->toBe($people)
        ->and(Acknowledgment::query()->count())->toBe($people);
})->with([499, 500, 501]);

it('asks once per person: asking again, or after new people arrive, only adds who is missing', function (): void {
    staffOf($this->tenant, $this->north, 2);
    $policy = policyReaching([$this->north]);
    $action = resolve(RequestAcknowledgments::class);

    expect($action->handle($policy->id))->toBe(2)
        ->and($action->handle($policy->id))->toBe(0);

    staffOf($this->tenant, $this->north, 1);

    expect($action->handle($policy->id))->toBe(1)
        ->and(Acknowledgment::query()->count())->toBe(3);
});

it('keeps the requests of older versions: a new version asks again', function (): void {
    staffOf($this->tenant, $this->north, 2);
    $first = policyReaching([$this->north], 1);
    $second = policyReaching([$this->north], 2);
    $action = resolve(RequestAcknowledgments::class);

    $action->handle($first->id);
    $action->handle($second->id);

    expect(Acknowledgment::query()->count())->toBe(4)
        ->and(askedFor($second))->toBe(askedFor($first));
});

it('asks for the version at once when it is published, through the queue after the commit', function (): void {
    expect(is_subclass_of(RequestOnPublication::class, ShouldQueue::class))->toBeTrue()
        ->and(is_subclass_of(RequestOnPublication::class, ShouldQueueAfterCommit::class))->toBeTrue();

    $people = staffOf($this->tenant, $this->north, 2);
    $draft = resolve(App\Modules\Disclosure\Actions\StartPolicyDraft::class)->handle(null);
    resolve(App\Modules\Disclosure\Actions\SaveDraft::class)->handle($draft->id, array_map(fn (array $section): array => ['key' => $section['key'], 'text' => in_array($section['key'], ['responsibles', 'resources'], true) ? 'RH' : $section['text'], 'items' => $section['items']], $draft->content));

    resolve(App\Modules\Disclosure\Actions\PublishPolicy::class)->handle($draft->id, [$this->north->id], ['a', 'b', 'c'], true, $this->hr->id);

    expect(Acknowledgment::query()->count())->toBe(2)
        ->and(Acknowledgment::query()->pluck('employee_id')->sort()->values()->all())->toBe($people->pluck('id')->sort()->values()->all());
});

it('asks whoever joins a center after its policy was published: a new hire, a reactivated or a moved-in person', function (string $event): void {
    expect(is_subclass_of(RequestPolicyFromNewStaff::class, ShouldQueue::class))->toBeTrue()
        ->and(is_subclass_of(RequestPolicyFromNewStaff::class, ShouldQueueAfterCommit::class))->toBeTrue();

    $policy = policyReaching([$this->north]);
    $person = staffOf($this->tenant, $this->north, 1)->first();

    event(match ($event) {
        'created' => new EmployeeCreated($this->tenant->id, $person->id, $this->north->id),
        'reactivated' => new EmployeeReactivated($this->tenant->id, $person->id, $this->north->id),
        'moved' => new EmployeeMoved($this->tenant->id, $person->id, $this->south->id, $this->north->id),
    });

    expect(askedFor($policy))->toBe([$person->id]);

    event(new EmployeeCreated($this->tenant->id, $person->id, $this->north->id));
    expect(Acknowledgment::query()->count())->toBe(1);
})->with(['created', 'reactivated', 'moved']);

it('asks a person who moves in for the version in force of the new center, not of the old one', function (): void {
    $forNorth = policyReaching([$this->north], 1);
    $forSouth = policyReaching([$this->south], 2);
    $person = staffOf($this->tenant, $this->south, 1)->first();

    event(new EmployeeMoved($this->tenant->id, $person->id, $this->north->id, $this->south->id));

    expect(askedFor($forSouth))->toBe([$person->id])
        ->and(askedFor($forNorth))->toBe([]);
});

it('asks no one for a center without policy, a person who left or one of another company', function (): void {
    policyReaching([$this->north]);
    $inSouth = staffOf($this->tenant, $this->south, 1)->first();
    $left = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id]);

    event(new EmployeeCreated($this->tenant->id, $inSouth->id, $this->south->id));
    event(new EmployeeReactivated($this->tenant->id, $left->id, $this->north->id));

    $other = Tenant::factory()->create();
    $foreign = Employee::factory()->create(['tenant_id' => $other->id]);
    event(new EmployeeCreated($this->tenant->id, $foreign->id, $this->north->id));

    expect(Acknowledgment::query()->count())->toBe(0);
});

it('leaves the requests of a person who leaves, and counts them out of the audience', function (): void {
    $person = staffOf($this->tenant, $this->north, 2);
    $policy = policyReaching([$this->north]);
    resolve(RequestAcknowledgments::class)->handle($policy->id);

    $person[0]->update(['deactivated_at' => now()]);

    expect(Acknowledgment::query()->count())->toBe(2);
});

it('does not ask for the version of another company', function (): void {
    $other = Tenant::factory()->create();
    $foreign = Document::factory()->published()->policy()->create(['tenant_id' => $other->id]);

    expect(fn () => resolve(RequestAcknowledgments::class)->handle($foreign->id))->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('logs how many were asked, with ids only', function (): void {
    Log::spy();
    staffOf($this->tenant, $this->north, 2);
    $policy = policyReaching([$this->north]);

    resolve(RequestAcknowledgments::class)->handle($policy->id);

    Log::shouldHaveReceived('info')->with('disclosure.acknowledgment_requested', ['document_id' => $policy->id, 'employees' => 2])->once();
});

it('keeps every request when the mail quota runs out: nothing is mailed, nothing is undone, and the person still sees it pending', function (): void {
    Queue::fake();
    Log::spy();
    staffOf($this->tenant, $this->north, 3);
    $policy = policyReaching([$this->north]);
    withMailQuota($this->tenant, 2);

    $made = resolve(RequestAcknowledgments::class)->handle($policy->id);

    expect($made)->toBe(3)
        ->and(askedFor($policy))->toHaveCount(3);
    Queue::assertNotPushed(SendPolicyReminder::class);
    Log::shouldHaveReceived('info')->with('disclosure.acknowledgment_requested', ['document_id' => $policy->id, 'employees' => 3])->once();
    Log::shouldHaveReceived('info')->with('disclosure.acknowledgment_mail_withheld', ['document_id' => $policy->id, 'employees' => 3])->once();
});

it('holds the mail only for who has an address: the quota is not spent on people the email cannot reach', function (): void {
    Queue::fake();
    $people = staffOf($this->tenant, $this->north, 3);
    $people[0]->update(['email' => null]);
    $policy = policyReaching([$this->north]);
    withMailQuota($this->tenant, 2);

    expect(resolve(RequestAcknowledgments::class)->handle($policy->id))->toBe(3);

    expect(askedFor($policy))->toHaveCount(3);
    Queue::assertPushed(SendPolicyReminder::class, 2);
    Queue::assertNotPushed(SendPolicyReminder::class, fn (SendPolicyReminder $job): bool => $job->employeeId === $people[0]->id);
});

it('keeps the request of a newcomer too when the quota is gone: the arrival is never undone by the mail', function (): void {
    Queue::fake();
    $person = staffOf($this->tenant, $this->north, 1)->first();
    $policy = policyReaching([$this->north]);
    withMailQuota($this->tenant, 1);
    resolve(App\Modules\Billing\Contracts\MailQuota::class)->reserve(1);

    resolve(RequestPolicyFromNewStaff::class)->handle(new EmployeeCreated($this->tenant->id, $person->id, $this->north->id));

    expect(askedFor($policy))->toBe([$person->id]);
    Queue::assertNotPushed(SendPolicyReminder::class);
});

it('keeps the requests when they fit the quota exactly', function (): void {
    staffOf($this->tenant, $this->north, 3);
    $policy = policyReaching([$this->north]);
    withMailQuota($this->tenant, 3);

    expect(resolve(RequestAcknowledgments::class)->handle($policy->id))->toBe(3);
});

it('asks nobody while the company has no NOM-035: the module is off, so no request and no mail are made', function (string $event): void {
    Queue::fake();
    $person = staffOf($this->tenant, $this->north, 1)->first();
    $policy = policyReaching([$this->north]);
    resolve(App\Modules\Tenancy\Contracts\CompanyModules::class)->syncPlan($this->tenant->id, ['seating'], Carbon\CarbonImmutable::now(config('app.display_timezone')));

    match ($event) {
        'publication' => resolve(RequestOnPublication::class)->handle(new App\Modules\Disclosure\Events\PolicyPublished($this->tenant->id, $policy->id, [$this->north->id])),
        'created' => resolve(RequestPolicyFromNewStaff::class)->handle(new EmployeeCreated($this->tenant->id, $person->id, $this->north->id)),
        'reactivated' => resolve(RequestPolicyFromNewStaff::class)->handle(new EmployeeReactivated($this->tenant->id, $person->id, $this->north->id)),
        'moved' => resolve(RequestPolicyFromNewStaff::class)->handle(new EmployeeMoved($this->tenant->id, $person->id, $this->south->id, $this->north->id)),
    };

    expect(Acknowledgment::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with(['publication', 'created', 'reactivated', 'moved']);

it('asks again when the company gets NOM-035 back: the guard only holds while the module is off', function (): void {
    $person = staffOf($this->tenant, $this->north, 1)->first();
    $policy = policyReaching([$this->north]);
    $modules = resolve(App\Modules\Tenancy\Contracts\CompanyModules::class);
    $modules->syncPlan($this->tenant->id, ['seating'], Carbon\CarbonImmutable::now(config('app.display_timezone')));
    resolve(RequestOnPublication::class)->handle(new App\Modules\Disclosure\Events\PolicyPublished($this->tenant->id, $policy->id, [$this->north->id]));
    $modules->syncPlan($this->tenant->id, ['nom035'], Carbon\CarbonImmutable::now(config('app.display_timezone')));

    resolve(RequestOnPublication::class)->handle(new App\Modules\Disclosure\Events\PolicyPublished($this->tenant->id, $policy->id, [$this->north->id]));

    expect(askedFor($policy))->toBe([$person->id]);
});

it('retries a failed request by itself: three tries, waiting a minute and then five, whatever Horizon says', function (string $event, string $listener): void {
    Queue::fake();
    $person = staffOf($this->tenant, $this->north, 1)->first();
    $policy = policyReaching([$this->north]);

    Event::dispatch(match ($event) {
        'publication' => new App\Modules\Disclosure\Events\PolicyPublished($this->tenant->id, $policy->id, [$this->north->id]),
        'created' => new EmployeeCreated($this->tenant->id, $person->id, $this->north->id),
        'reactivated' => new EmployeeReactivated($this->tenant->id, $person->id, $this->north->id),
        'moved' => new EmployeeMoved($this->tenant->id, $person->id, $this->south->id, $this->north->id),
    });

    $jobs = Queue::pushed(CallQueuedListener::class, fn (CallQueuedListener $job): bool => $job->class === $listener);
    expect($jobs)->toHaveCount(1)
        ->and($jobs->first()->tries)->toBe(3)
        ->and($jobs->first()->backoff)->toBe([60, 300]);
})->with([
    'publication' => ['publication', RequestOnPublication::class],
    'created' => ['created', RequestPolicyFromNewStaff::class],
    'reactivated' => ['reactivated', RequestPolicyFromNewStaff::class],
    'moved' => ['moved', RequestPolicyFromNewStaff::class],
]);

/** A listener running as the worker's second try would: the first one failed. */
function asRetry(object $listener): object
{
    $job = Mockery::mock(Illuminate\Contracts\Queue\Job::class);
    $job->shouldReceive('attempts')->andReturn(2);
    $listener->setJob($job);

    return $listener;
}

it('mails on the retry the ones whose request a failed first try left unmailed, and nobody who confirmed, left or has no address', function (): void {
    Queue::fake();
    [$waiting, $confirmed, $paper] = staffOf($this->tenant, $this->north, 3)->all();
    $paper->update(['email' => null]);
    $left = Employee::factory()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'area_id' => $waiting->area_id]);
    $policy = policyReaching([$this->north]);
    // What the first try left: the requests are there, the mail never went.
    foreach ([$waiting, $confirmed, $paper, $left] as $person) {
        Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $person->id, ...($person->is($confirmed) ? ['acknowledged_at' => now(), 'channel' => 'portal'] : [])]);
    }

    $event = new App\Modules\Disclosure\Events\PolicyPublished($this->tenant->id, $policy->id, [$this->north->id]);
    resolve(RequestOnPublication::class)->handle($event);
    Queue::assertNothingPushed();

    asRetry(resolve(RequestOnPublication::class))->handle($event);

    Queue::assertPushed(SendPolicyReminder::class, 1);
    Queue::assertPushed(SendPolicyReminder::class, fn (SendPolicyReminder $job): bool => $job->employeeId === $waiting->id);
});

it('mails on the retry the newcomer whose request was kept but not mailed, and holds the quota for that one mail', function (): void {
    Queue::fake();
    $person = staffOf($this->tenant, $this->north, 1)->first();
    $policy = policyReaching([$this->north]);
    Acknowledgment::factory()->create(['tenant_id' => $this->tenant->id, 'document_id' => $policy->id, 'employee_id' => $person->id]);
    withMailQuota($this->tenant, 1);
    $event = new EmployeeCreated($this->tenant->id, $person->id, $this->north->id);

    resolve(RequestPolicyFromNewStaff::class)->handle($event);
    Queue::assertNothingPushed();

    asRetry(resolve(RequestPolicyFromNewStaff::class))->handle($event);

    Queue::assertPushed(SendPolicyReminder::class, fn (SendPolicyReminder $job): bool => $job->employeeId === $person->id);
    expect(Acknowledgment::query()->count())->toBe(1)
        ->and(App\Modules\Billing\Models\UsageCounter::query()->where('meter', 'email_campaign')->sole()->reserved)->toBe(1);
});
