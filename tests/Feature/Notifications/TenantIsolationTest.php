<?php

declare(strict_types=1);

use App\Modules\Notifications\Actions\ForgetSubscriptions;
use App\Modules\Notifications\Enums\PushKind;
use App\Modules\Notifications\Jobs\DeliverPush;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Notifications\Support\PushNotifier;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;

mutates(ForgetSubscriptions::class);

/*
 * Company A reads, deletes and notifies none of company B's subscriptions, by query, by address and by event.
 */
beforeEach(function (): void {
    $this->a = Tenant::factory()->create(['slug' => 'acme']);
    $this->b = Tenant::factory()->create(['slug' => 'beta']);
    $this->anaOfA = EmployeeFactory::new()->create(['tenant_id' => $this->a->id]);
    $this->betoOfB = EmployeeFactory::new()->create(['tenant_id' => $this->b->id]);
    $this->ofA = PushSubscription::factory()->create(['tenant_id' => $this->a->id, 'employee_id' => $this->anaOfA->id]);
    $keys = VAPID::createVapidKeys();
    config(['services.vapid' => ['public_key' => $keys['publicKey'], 'private_key' => $keys['privateKey'], 'subject' => 'mailto:soporte@humetria.com']]);
});

it('does not read the subscriptions of another company, nor forget them', function (): void {
    workIn($this->b);

    expect(PushSubscription::query()->count())->toBe(0)
        ->and(resolve(ForgetSubscriptions::class)->handle($this->anaOfA->id, null, 'revoked'))->toBe(0)
        ->and(resolve(ForgetSubscriptions::class)->handle($this->anaOfA->id, $this->ofA->endpoint, 'unsubscribed'))->toBe(0);

    workIn($this->a);
    expect(PushSubscription::query()->count())->toBe(1);
});

it('does not let the session of A do anything in B', function (): void {
    asPerson($this->anaOfA->id);

    $this->deleteJson(route('portal.push.destroy', 'beta'), ['endpoint' => $this->ofA->endpoint])->assertUnauthorized();

    workIn($this->a);
    expect(PushSubscription::query()->count())->toBe(1);
});

it('does not delete the address of A with the session of B: 204 and the row stays', function (): void {
    $slug = asPerson($this->betoOfB->id);

    $this->deleteJson(route('portal.push.destroy', $slug), ['endpoint' => $this->ofA->endpoint])->assertNoContent();

    workIn($this->a);
    expect(PushSubscription::query()->count())->toBe(1);
});

it('does not move an address of A when B subscribes with the same one: each company has its own row', function (): void {
    $slug = asPerson($this->betoOfB->id);

    $this->postJson(route('portal.push.store', $slug), ['endpoint' => $this->ofA->endpoint, 'keys' => ['p256dh' => str_repeat('a', 87), 'auth' => str_repeat('b', 22)]])->assertNoContent();

    workIn($this->a);
    expect(PushSubscription::query()->sole()->employee_id)->toBe($this->anaOfA->id);
    workIn($this->b);
    expect(PushSubscription::query()->sole()->employee_id)->toBe($this->betoOfB->id);
});

it('answers 404 to a company that does not exist', function (): void {
    asPerson($this->anaOfA->id);

    $this->deleteJson(route('portal.push.destroy', 'nobody'), ['endpoint' => $this->ofA->endpoint])->assertNotFound();
});

it('does not touch the rows of A when access is revoked in B', function (): void {
    workIn($this->b);

    event(new WorkerAccessRevoked($this->anaOfA->id, 'reissued'));

    workIn($this->a);
    expect(PushSubscription::query()->count())->toBe(1);
});

it('queues nothing in B for a person of A', function (): void {
    Queue::fake();
    workIn($this->b);

    resolve(PushNotifier::class)->notify($this->anaOfA->id, PushKind::MarkReviewed);

    Queue::assertNotPushed(DeliverPush::class);
});
