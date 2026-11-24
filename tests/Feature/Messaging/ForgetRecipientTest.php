<?php

declare(strict_types=1);

use App\Modules\Messaging\Actions\ForgetRecipient;
use App\Modules\Messaging\Models\Delivery;
use App\Modules\Messaging\Models\DeliveryEvent;
use App\Modules\Messaging\Models\OptOut;
use App\Modules\Messaging\Support\RecipientHash;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Messaging\DeliveryEventFactory;
use Database\Factories\Messaging\DeliveryFactory;
use Database\Factories\Messaging\OptOutFactory;
use Illuminate\Support\Facades\Log;

mutates(ForgetRecipient::class);

const FORGOTTEN_PHONE = '+528186875767';

beforeEach(function (): void {
    config(['messaging.recipient_keys' => [1 => base64_encode('a-key')]]);
});

function deliveryTo(string $phone, ?Tenant $tenant = null, int $key = 1): Delivery
{
    $delivery = DeliveryFactory::new()->create(['tenant_id' => ($tenant ?? Tenant::factory()->create())->id, 'recipient_hash' => (new RecipientHash)->all($phone)[$key - 1], 'key_version' => $key]);
    DeliveryEventFactory::new()->create(['delivery_id' => $delivery->id]);

    return $delivery;
}

it('deletes every message of a phone, of every company, with its history, and no one else\'s', function (): void {
    $a = deliveryTo(FORGOTTEN_PHONE);
    $b = deliveryTo(FORGOTTEN_PHONE);
    $other = deliveryTo('+528100000000');

    $deleted = resolve(ForgetRecipient::class)->handle(FORGOTTEN_PHONE);

    expect($deleted)->toBe(2)
        ->and(Delivery::query()->whereKey([$a->id, $b->id])->exists())->toBeFalse()
        ->and(DeliveryEvent::query()->whereIn('delivery_id', [$a->id, $b->id])->exists())->toBeFalse()
        ->and(Delivery::query()->whereKey($other->id)->exists())->toBeTrue()
        ->and(DeliveryEvent::query()->where('delivery_id', $other->id)->exists())->toBeTrue();
});

it('finds the messages written under an older key too', function (): void {
    deliveryTo(FORGOTTEN_PHONE);
    config(['messaging.recipient_keys' => [1 => base64_encode('a-key'), 2 => base64_encode('newer')]]);
    $newer = deliveryTo(FORGOTTEN_PHONE, key: 2);

    expect(resolve(ForgetRecipient::class)->handle(FORGOTTEN_PHONE))->toBe(2)
        ->and(Delivery::query()->whereKey($newer->id)->exists())->toBeFalse();
});

it('does not delete the opt-out: forgetting a person is not writing to them again', function (): void {
    deliveryTo(FORGOTTEN_PHONE);
    ['hash' => $hash, 'version' => $version] = (new RecipientHash)->current(FORGOTTEN_PHONE);
    OptOutFactory::new()->create(['recipient_hash' => $hash, 'key_version' => $version]);

    resolve(ForgetRecipient::class)->handle(FORGOTTEN_PHONE);

    expect(OptOut::query()->count())->toBe(1);
});

it('leaves the mail rows alone: they keep neither an address nor a hash', function (): void {
    $mail = DeliveryFactory::new()->mail()->create();

    resolve(ForgetRecipient::class)->handle(FORGOTTEN_PHONE);

    expect(Delivery::query()->whereKey($mail->id)->exists())->toBeTrue();
});

it('says how many it deleted and never the phone', function (): void {
    Log::spy();
    deliveryTo(FORGOTTEN_PHONE);

    resolve(ForgetRecipient::class)->handle(FORGOTTEN_PHONE);

    Log::shouldHaveReceived('info')->withArgs(fn (string $event, array $context = []): bool => $event === 'messaging.recipient_forgotten'
        && $context === ['deleted' => 1])->once();
});

it('asks for the phone without echoing it, and normalizes it', function (): void {
    deliveryTo(FORGOTTEN_PHONE);

    $this->artisan('messaging:forget')->expectsQuestion('Teléfono de la persona', '81 8687 5767')->expectsOutputToContain('Mensajes borrados: 1')->assertSuccessful();

    expect(Delivery::query()->count())->toBe(0);
});

it('refuses a phone that is not one', function (): void {
    $this->artisan('messaging:forget')->expectsQuestion('Teléfono de la persona', 'hola')->assertFailed();
});
