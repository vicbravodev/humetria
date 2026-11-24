<?php

declare(strict_types=1);

use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\OptOut;
use Database\Factories\Messaging\DeliveryEventFactory;
use Database\Factories\Messaging\DeliveryFactory;
use Database\Factories\Messaging\OptOutFactory;
use Database\Factories\Messaging\WebhookEventFactory;
use Database\Factories\Tenancy\TenantFactory;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** The message of the database refusing the insert, or null when it let it in; inside a savepoint, so the test goes on. */
function refusedInsert(Closure $insert): ?string
{
    try {
        DB::transaction($insert);
    } catch (QueryException $refusal) {
        return $refusal->getMessage();
    }

    return null;
}

it('has factories that make valid rows', function (): void {
    expect(DeliveryFactory::new()->createOne())->toBeInstanceOf(Delivery::class)
        ->and(DeliveryEventFactory::new()->createOne()->delivery_id)->not->toBeEmpty()
        ->and(OptOutFactory::new()->createOne())->toBeInstanceOf(OptOut::class)
        ->and(WebhookEventFactory::new()->createOne()->event_id)->not->toBeEmpty();
});

it('refuses each value outside its CHECK', function (string $table, array $override, string $constraint): void {
    $factory = match ($table) {
        'messaging_deliveries' => DeliveryFactory::new(),
        'messaging_opt_outs' => OptOutFactory::new(),
        'messaging_webhook_events' => WebhookEventFactory::new(),
        'messaging_delivery_events' => DeliveryEventFactory::new(),
    };

    // Raw, so the model's own casts do not refuse the value before the database does.
    $row = collect($factory->raw($override))
        ->map(fn (mixed $value): mixed => $value instanceof BackedEnum ? $value->value : $value)
        ->put('id', (string) Str::uuid())
        ->when(Schema::hasColumn($table, 'created_at'), fn ($row) => $row->put('created_at', now()))
        ->all();

    expect(refusedInsert(fn () => DB::table($table)->insert($row)))->toContain($constraint);
})->with([
    'channel' => ['messaging_deliveries', ['channel' => 'sms'], 'messaging_deliveries_channel_check'],
    'whatsapp without a template' => ['messaging_deliveries', ['template' => null], 'messaging_deliveries_template_check'],
    'whatsapp without a recipient hash' => ['messaging_deliveries', ['recipient_hash' => null, 'key_version' => null], 'messaging_deliveries_recipient_check'],
    'a hash without its key version' => ['messaging_deliveries', ['key_version' => null], 'messaging_deliveries_key_version_check'],
    'invitation type' => ['messaging_deliveries', ['invitation_type' => 'other'], 'messaging_deliveries_invitation_type_check'],
    'source type' => ['messaging_deliveries', ['source_type' => 'other'], 'messaging_deliveries_source_type_check'],
    'status' => ['messaging_deliveries', ['status' => 'lost'], 'messaging_deliveries_status_check'],
    'event status' => ['messaging_delivery_events', ['status' => 'lost'], 'messaging_delivery_events_status_check'],
    'event failure cause' => ['messaging_delivery_events', ['failure_cause' => 'gremlins'], 'messaging_delivery_events_failure_cause_check'],
    'opt-out source' => ['messaging_opt_outs', ['source' => 'friend'], 'messaging_opt_outs_source_check'],
    'webhook provider' => ['messaging_webhook_events', ['provider' => 'twilio'], 'messaging_webhook_events_provider_check'],
]);

it('lets a mail delivery have neither template nor hash', function (): void {
    expect(DeliveryFactory::new()->mail()->createOne()->recipient_hash)->toBeNull();
});

it('refuses the same provider message id twice in one channel', function (): void {
    DeliveryFactory::new()->createOne(['provider_message_id' => 'wamid.1']);

    expect(refusedInsert(fn () => DeliveryFactory::new()->createOne(['provider_message_id' => 'wamid.1'])))->toContain('provider_message_id');
});

it('lets many deliveries wait without a provider message id', function (): void {
    DeliveryFactory::new()->count(2)->create(['provider_message_id' => null]);

    expect(Delivery::query()->whereNull('provider_message_id')->count())->toBe(2);
});

it('makes two global opt-outs of the same hash collide, and one per company coexist', function (): void {
    OptOutFactory::new()->global()->createOne(['recipient_hash' => str_repeat('a', 64)]);

    expect(refusedInsert(fn () => OptOutFactory::new()->global()->createOne(['recipient_hash' => str_repeat('a', 64)])))->toContain('unique');

    OptOutFactory::new()->createOne(['recipient_hash' => str_repeat('a', 64)]);
    expect(OptOut::query()->where('recipient_hash', str_repeat('a', 64))->count())->toBe(2);
});

it('does not let a real company with messages be deleted', function (): void {
    $delivery = DeliveryFactory::new()->createOne();

    expect(refusedInsert(fn () => DB::table('tenants')->where('id', $delivery->tenant_id)->delete()))->toContain('messaging_deliveries');
});

it('refuses an event for a delivery of another company', function (): void {
    $delivery = DeliveryFactory::new()->createOne();
    $other = TenantFactory::new()->createOne();

    expect(refusedInsert(fn () => DeliveryEventFactory::new()->createOne(['delivery_id' => $delivery->id, 'tenant_id' => $other->id])))->toContain('foreign key');
});
