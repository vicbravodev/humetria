<?php

declare(strict_types=1);

use App\Modules\Messaging\Data\FailureCause;
use App\Modules\Messaging\Enums\DeliveryStatus;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Support\RecordDeliveryEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

mutates(RecordDeliveryEvent::class, DeliveryStatus::class);

function record(Delivery $delivery, DeliveryStatus $status, string $at = '2026-11-18 10:00:00', ?FailureCause $cause = null): void
{
    (new RecordDeliveryEvent)->handle($delivery, $status, CarbonImmutable::parse($at), $cause);
}

it('moves up the order created, sent, delayed, delivered, read', function (): void {
    $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Created]);

    foreach ([DeliveryStatus::Sent, DeliveryStatus::Delayed, DeliveryStatus::Delivered, DeliveryStatus::Read] as $status) {
        record($delivery, $status);
        expect($delivery->status)->toBe($status);
    }
});

it('keeps a delayed that arrives after delivered in the history without changing the current status', function (): void {
    $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Delivered]);

    record($delivery, DeliveryStatus::Delayed);

    expect($delivery->fresh()?->status)->toBe(DeliveryStatus::Delivered)
        ->and(DeliveryEvent::query()->where('delivery_id', $delivery->id)->where('status', 'delayed')->count())->toBe(1);
});

it('does not go back to sent after read', function (): void {
    $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Read]);

    record($delivery, DeliveryStatus::Sent);

    expect($delivery->fresh()?->status)->toBe(DeliveryStatus::Read);
});

it('lets a failure end any non-terminal status', function (DeliveryStatus $from, DeliveryStatus $terminal): void {
    $delivery = Delivery::factory()->create(['status' => $from]);

    record($delivery, $terminal, cause: FailureCause::NoWhatsappAccount);

    expect($delivery->fresh()?->status)->toBe($terminal);
})->with([
    'created to failed' => [DeliveryStatus::Created, DeliveryStatus::Failed],
    'sent to failed' => [DeliveryStatus::Sent, DeliveryStatus::Failed],
    'delivered to bounced' => [DeliveryStatus::Delivered, DeliveryStatus::Bounced],
    'read to complained' => [DeliveryStatus::Read, DeliveryStatus::Complained],
]);

it('never leaves a terminal status, yet keeps every later event', function (DeliveryStatus $terminal): void {
    $delivery = Delivery::factory()->create(['status' => $terminal]);

    record($delivery, DeliveryStatus::Delivered);
    record($delivery, DeliveryStatus::Failed);

    expect($delivery->fresh()?->status)->toBe($terminal)
        ->and(DeliveryEvent::query()->where('delivery_id', $delivery->id)->count())->toBe(2);
})->with([DeliveryStatus::Failed, DeliveryStatus::Bounced, DeliveryStatus::Complained]);

it('leaves unknown and paused as transitory: a later sent or delivered replaces them', function (DeliveryStatus $transitory): void {
    $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Created]);

    record($delivery, $transitory);
    expect($delivery->status)->toBe($transitory);

    record($delivery, DeliveryStatus::Delivered);
    expect($delivery->status)->toBe(DeliveryStatus::Delivered);
})->with([DeliveryStatus::Unknown, DeliveryStatus::Paused]);

it('stores every event with the provider time, the cause and the company of the delivery', function (): void {
    $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Created]);

    record($delivery, DeliveryStatus::Failed, '2026-11-18 10:05:09', FailureCause::RateLimited);

    $event = DeliveryEvent::query()->where('delivery_id', $delivery->id)->firstOrFail();
    expect($event->tenant_id)->toBe($delivery->tenant_id)
        ->and($event->status)->toBe(DeliveryStatus::Failed)
        ->and($event->failure_cause)->toBe(FailureCause::RateLimited)
        ->and($event->occurred_at->toIso8601String())->toBe('2026-11-18T10:05:09+00:00');
});

it('logs the change of status with a fixed event and no phone', function (): void {
    Log::spy();
    $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Created]);

    record($delivery, DeliveryStatus::Sent);

    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'messaging.delivery_status_changed'
        && $context === ['delivery_id' => $delivery->id, 'channel' => 'whatsapp', 'status' => 'sent', 'cause' => null])->once();
});

it('does not log when the status stays', function (): void {
    Log::spy();
    $delivery = Delivery::factory()->create(['status' => DeliveryStatus::Read]);

    record($delivery, DeliveryStatus::Delayed);

    Log::shouldNotHaveReceived('info');
});

it('counts delivered and read as delivered, and nothing else', function (): void {
    $delivered = array_filter(DeliveryStatus::cases(), fn (DeliveryStatus $status): bool => $status->isDelivered());

    expect(array_values(array_map(fn (DeliveryStatus $status): string => $status->value, $delivered)))->toBe(['delivered', 'read']);
});

it('refuses an update and a delete of an event, and accepts the delete of a purge', function (): void {
    $event = DeliveryEvent::factory()->create();

    $refused = function (string $sql) use ($event): ?string {
        try {
            DB::transaction(fn (): int => DB::affectingStatement($sql, [$event->id]));
        } catch (QueryException $exception) {
            return $exception->getMessage();
        }

        return null;
    };

    expect($refused('update messaging_delivery_events set status = status where id = ?'))->toContain('append-only')
        ->and($refused('delete from messaging_delivery_events where id = ?'))->toContain('append-only');

    DB::transaction(function () use ($event): void {
        DB::statement("SET LOCAL messaging.purge = 'on'");
        DB::table('messaging_delivery_events')->where('id', $event->id)->delete();
    });

    expect(DeliveryEvent::query()->whereKey($event->id)->exists())->toBeFalse();
});
