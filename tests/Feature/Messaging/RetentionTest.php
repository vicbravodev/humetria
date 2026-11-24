<?php

declare(strict_types=1);

use App\Modules\Messaging\Actions\PurgeMessaging;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Models\WebhookEvent;
use Database\Factories\Messaging\DeliveryEventFactory;
use Database\Factories\Messaging\DeliveryFactory;
use Database\Factories\Messaging\OptOutFactory;
use Database\Factories\Messaging\WebhookEventFactory;
use Illuminate\Support\Facades\Log;

mutates(PurgeMessaging::class);

beforeEach(function (): void {
    $this->travelTo('2027-03-15 12:00:00');
    config(['messaging.retention_months' => 12, 'messaging.webhook_event_days' => 7]);
});

function deliveryCreated(string $at): Delivery
{
    $delivery = DeliveryFactory::new()->create(['created_at' => $at, 'updated_at' => $at]);
    DeliveryEventFactory::new()->create(['delivery_id' => $delivery->id]);

    return $delivery;
}

it('deletes the messages older than the retention, with their history, and keeps the newer ones', function (): void {
    $old = deliveryCreated('2026-03-14 11:59:59');
    $edge = deliveryCreated('2026-03-15 12:00:01');
    $new = deliveryCreated('2027-03-01 12:00:00');

    $deleted = resolve(PurgeMessaging::class)->handle();

    expect($deleted['deliveries'])->toBe(1)
        ->and(Delivery::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(DeliveryEvent::query()->where('delivery_id', $old->id)->exists())->toBeFalse()
        ->and(Delivery::query()->whereKey([$edge->id, $new->id])->count())->toBe(2)
        ->and(DeliveryEvent::query()->whereIn('delivery_id', [$edge->id, $new->id])->count())->toBe(2);
});

it('keeps the history append-only for everyone else: the purge is the only way in', function (): void {
    $delivery = deliveryCreated('2026-01-01 00:00:00');
    $event = DeliveryEvent::query()->where('delivery_id', $delivery->id)->firstOrFail();

    expect(fn () => Illuminate\Support\Facades\DB::transaction(fn () => $event->delete()))->toThrow(Illuminate\Database\QueryException::class);

    resolve(PurgeMessaging::class)->handle();

    expect(DeliveryEvent::query()->whereKey($event->id)->exists())->toBeFalse();
});

it('deletes the webhook events older than seven days and keeps the others', function (): void {
    WebhookEventFactory::new()->create(['received_at' => '2027-03-08 11:59:59']);
    $edge = WebhookEventFactory::new()->create(['received_at' => '2027-03-08 12:00:01']);

    $deleted = resolve(PurgeMessaging::class)->handle();

    expect($deleted['webhook_events'])->toBe(1)->and(WebhookEvent::query()->sole()->id)->toBe($edge->id);
});

it('never deletes an opt-out, however old: it is what stops us writing again', function (): void {
    OptOutFactory::new()->create(['created_at' => '2020-01-01 00:00:00']);
    OptOutFactory::new()->global()->create(['created_at' => '2020-01-01 00:00:00']);

    resolve(PurgeMessaging::class)->handle();

    expect(OptOut::query()->count())->toBe(2);
});

it('uses the retention it is configured with', function (): void {
    config(['messaging.retention_months' => 3]);
    deliveryCreated('2026-12-01 00:00:00');
    deliveryCreated('2027-01-01 00:00:00');

    expect(resolve(PurgeMessaging::class)->handle()['deliveries'])->toBe(1);
});

it('says what it deleted, as numbers only', function (): void {
    Log::spy();
    deliveryCreated('2025-01-01 00:00:00');

    resolve(PurgeMessaging::class)->handle();

    Log::shouldHaveReceived('info')->with('messaging.purged', ['deliveries' => 1, 'webhook_events' => 0])->once();
});

it('runs from the command', function (): void {
    deliveryCreated('2025-01-01 00:00:00');

    $this->artisan('messaging:purge')->expectsOutputToContain('Mensajes borrados: 1')->assertSuccessful();
});
