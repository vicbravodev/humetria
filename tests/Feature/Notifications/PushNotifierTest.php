<?php

declare(strict_types=1);

use App\Modules\Notifications\Jobs\DeliverPush;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Notifications\Support\PushNotifier;
use App\Modules\Tenancy\Exceptions\NoCurrentTenant;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;

mutates(PushNotifier::class);

/*
 * «Tiene un aviso en Humetría»: the notifier sends a job per phone of the person, with nothing to say: it has no place to put content.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $keys = VAPID::createVapidKeys();
    config(['services.vapid' => ['public_key' => $keys['publicKey'], 'private_key' => $keys['privateKey'], 'subject' => 'mailto:soporte@humetria.com']]);
    Queue::fake();
});

it('sends a job for each phone of the person and none for another person', function (): void {
    $phones = PushSubscription::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id]);

    resolve(PushNotifier::class)->notify($this->ana->id);

    Queue::assertPushed(DeliverPush::class, 2);
    foreach ($phones as $phone) {
        Queue::assertPushed(DeliverPush::class, fn (DeliverPush $job): bool => $job->subscriptionId === $phone->id && $job->tenantId === $this->tenant->id);
    }
});

it('sends nothing for a person with no phone', function (): void {
    resolve(PushNotifier::class)->notify($this->ana->id);

    Queue::assertNothingPushed();
});

it('sends nothing, saying so, when the keys are missing', function (): void {
    config(['services.vapid.public_key' => null]);
    PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    Log::spy();

    resolve(PushNotifier::class)->notify($this->ana->id);

    Queue::assertNothingPushed();
    Log::shouldHaveReceived('warning')->with('push.unconfigured', ['reason' => 'missing_keys'])->once();
});

it('refuses a subject that is not mailto: or https://, and takes one that is', function (string $subject, bool $sends): void {
    config(['services.vapid.subject' => $subject]);
    PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    Log::spy();

    resolve(PushNotifier::class)->notify($this->ana->id);

    $sends ? Queue::assertPushed(DeliverPush::class) : Queue::assertNothingPushed();
    if (! $sends) {
        Log::shouldHaveReceived('warning')->with('push.unconfigured', ['reason' => 'subject_invalid'])->once();
    }
})->with([
    'http' => ['http://x', false],
    'an email without mailto' => ['x@y', false],
    'empty' => ['', false],
    'mailto' => ['mailto:a@b', true],
    'https' => ['https://humetria.com', true],
]);

it('does not notify the demo company: nothing leaves it', function (): void {
    $demo = Tenant::factory()->demo()->create();
    workIn($demo);
    $person = EmployeeFactory::new()->create(['tenant_id' => $demo->id]);
    PushSubscription::factory()->create(['tenant_id' => $demo->id, 'employee_id' => $person->id]);
    Log::spy();

    resolve(PushNotifier::class)->notify($person->id);

    Queue::assertNothingPushed();
    Log::shouldHaveReceived('debug')->with('push.demo_blocked')->once();
});

// The fake queue does not wait for a commit (the real one does, by this interface), so what is checked is that the job asks for it.
it('waits for the commit of the transaction that said it: if that is undone, nothing is sent', function (): void {
    expect(new DeliverPush($this->tenant->id, 'any'))->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

it('fails outside a company', function (): void {
    Context::forget('tenant_id');

    expect(fn () => resolve(PushNotifier::class)->notify($this->ana->id))->toThrow(NoCurrentTenant::class);
});

it('has no parameter for content: it takes the person and nothing else', function (): void {
    expect((new ReflectionMethod(PushNotifier::class, 'notify'))->getNumberOfParameters())->toBe(1);
});
