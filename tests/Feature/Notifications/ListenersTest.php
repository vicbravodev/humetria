<?php

declare(strict_types=1);

use App\Modules\Attendance\Actions\ResolveCorrection;
use App\Modules\Attendance\Actions\ReviewOfflineMark;
use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\ReviewDecision;
use App\Modules\Attendance\Events\CorrectionResolved;
use App\Modules\Attendance\Events\MarkCorrected;
use App\Modules\Attendance\Events\OfflineMarkReviewed;
use App\Modules\Attendance\Models\AttendanceMark;
use App\Modules\Attendance\Models\CorrectionRequest;
use App\Modules\Notifications\Jobs\DeliverPush;
use App\Modules\Notifications\Listeners\ForgetOnAccessRevoked;
use App\Modules\Notifications\Listeners\NotifyCorrectionResolved;
use App\Modules\Notifications\Listeners\NotifyMarkReviewed;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use App\Modules\WorkerAccess\Actions\RevokeCredential;
use App\Modules\WorkerAccess\Events\WorkerAccessRevoked;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\VAPID;
use Tests\Support\OfflineDeliveries;

mutates(NotifyMarkReviewed::class, NotifyCorrectionResolved::class, ForgetOnAccessRevoked::class);

/*
 * Two things of Attendance tell the person (a mark made without connection was reviewed; a request to correct was answered), and
 * three of WorkerAccess take their phone's subscription away. Each is tested alone, and once through the real action, so a
 * action that stops raising its event is noticed.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create();
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create();
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $keys = VAPID::createVapidKeys();
    config(['services.vapid' => ['public_key' => $keys['publicKey'], 'private_key' => $keys['privateKey'], 'subject' => 'mailto:soporte@humetria.com']]);
    $this->phones = PushSubscription::factory()->count(2)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
    $this->betoPhone = PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id]);
    Queue::fake();
});

describe('telling the person', function (): void {
    it('notifies each phone of the person when a mark was reviewed, whatever the decision', function (string $decision): void {
        event(new OfflineMarkReviewed('mark-1', $this->ana->id, $decision));

        Queue::assertPushed(DeliverPush::class, 2);
        Queue::assertNotPushed(DeliverPush::class, fn (DeliverPush $job): bool => $job->subscriptionId === $this->betoPhone->id);
    })->with(['verified', 'incorrect']);

    it('notifies each phone of the person when a request was answered, approved or rejected', function (bool $approved): void {
        event(new CorrectionResolved('request-1', $this->ana->id, $approved));

        Queue::assertPushed(DeliverPush::class, 2);
    })->with([true, false]);

    it('does not notify for a correction RH made on its own, nor for the one that follows an approval: the approval says it', function (): void {
        $mark = AttendanceMark::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'work_center_id' => $this->plant->id, 'marked_at' => now()->subDay()]);

        event(new MarkCorrected($mark->id, null, $this->ana->id));

        Queue::assertNothingPushed();
    });
});

describe('giving the phones up when the access is taken away', function (): void {
    it('deletes every subscription of the person and leaves the ones of other people', function (string $reason): void {
        event(new WorkerAccessRevoked($this->ana->id, $reason));

        expect(PushSubscription::query()->pluck('id')->all())->toBe([$this->betoPhone->id]);
    })->with(['revoked_by_hr', 'reissued', 'not_me', 'left_company']);

    it('logs only what it forgot: a person with no phone leaves no line, as the actions that raise the event expect', function (): void {
        $carla = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
        Log::spy();

        event(new WorkerAccessRevoked($carla->id, 'reissued'));
        event(new WorkerAccessRevoked($this->ana->id, 'reissued'));

        Log::shouldHaveReceived('info')->with('push.subscriptions_forgotten', ['count' => 2, 'reason' => 'revoked'])->once();
        Log::shouldNotHaveReceived('info', ['push.subscriptions_forgotten', ['count' => 0, 'reason' => 'revoked']]);
    });

    it('does not fail when said again, with nothing to delete', function (): void {
        event(new WorkerAccessRevoked($this->ana->id, 'reissued'));
        event(new WorkerAccessRevoked($this->ana->id, 'reissued', 'key-1'));

        expect(PushSubscription::query()->count())->toBe(1);
    });
});

describe('through the real actions', function (): void {
    it('tells the person when RH rejects their request', function (): void {
        $request = CorrectionRequest::factory()->create([
            'tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'mark_id' => null, 'type' => MarkType::Start,
            'proposed_at' => CarbonImmutable::parse('2026-11-04 09:00', 'America/Mexico_City')->utc(), 'reason' => 'Olvidé marcar el miércoles.',
        ]);

        resolve(ResolveCorrection::class)->handle($request->id, false, $this->hr->id, 'No coincide con la cámara.');

        Queue::assertPushed(DeliverPush::class, 2);
    });

    it('tells the person when RH verifies a mark they made without connection', function (): void {
        $delivery = OfflineDeliveries::accepted($this->ana, '2026-11-02 09:00');

        resolve(ReviewOfflineMark::class)->handle($delivery->mark_id ?? '', ReviewDecision::Verified, $this->hr->id, null, null);

        Queue::assertPushed(DeliverPush::class, 2);
    });

    it('gives the phones up when RH revokes a key of the person', function (): void {
        $key = WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

        resolve(RevokeCredential::class)->handle($key->id);

        expect(PushSubscription::query()->pluck('id')->all())->toBe([$this->betoPhone->id]);
    });
});
