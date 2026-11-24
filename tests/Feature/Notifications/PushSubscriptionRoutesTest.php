<?php

declare(strict_types=1);

use App\Modules\Notifications\Actions\StoreSubscription;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;

mutates(StoreSubscription::class);

/*
 * The person allows the notices on their phone: the page sends what the browser gave (`subscription.toJSON()`), with their session
 * as the only credential. Whose it is comes from the session, never from the body.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $this->beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
    $keys = VAPID::createVapidKeys();
    config(['services.vapid' => ['public_key' => $keys['publicKey'], 'private_key' => $keys['privateKey'], 'subject' => 'mailto:soporte@humetria.com']]);
});

/**
 * What `PushSubscription.toJSON()` gives.
 *
 * @param  array<string, mixed>  $override
 * @return array<string, mixed>
 */
function pushBody(array $override = []): array
{
    return [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::uuid(),
        'keys' => ['p256dh' => Str::random(87), 'auth' => Str::random(22)],
        ...$override,
    ];
}

describe('subscribing', function (): void {
    it('keeps the subscription of the person of the session and ignores whose the body says it is', function (): void {
        $slug = asPerson($this->ana->id);
        $other = Tenant::factory()->create();

        $this->postJson(route('portal.push.store', $slug), [...pushBody(), 'tenant_id' => $other->id, 'employee_id' => $this->beto->id])->assertNoContent();

        $saved = PushSubscription::query()->sole();
        expect($saved->tenant_id)->toBe($this->tenant->id)->and($saved->employee_id)->toBe($this->ana->id);
    });

    it('keeps one row for the same address said twice', function (): void {
        $slug = asPerson($this->ana->id);
        $body = pushBody();

        $this->postJson(route('portal.push.store', $slug), $body)->assertNoContent();
        $this->postJson(route('portal.push.store', $slug), [...$body, 'keys' => ['p256dh' => Str::random(87), 'auth' => Str::random(22)]])->assertNoContent();

        expect(PushSubscription::query()->count())->toBe(1);
    });

    it('gives a shared phone to the last person who subscribed on it', function (): void {
        $body = pushBody();
        $this->postJson(route('portal.push.store', asPerson($this->ana->id)), $body)->assertNoContent();
        $this->postJson(route('portal.push.store', asPerson($this->beto->id)), $body)->assertNoContent();

        expect(PushSubscription::query()->sole()->employee_id)->toBe($this->beto->id);
    });

    it('keeps up to five phones of a person and drops the oldest on the sixth', function (): void {
        $slug = asPerson($this->ana->id);
        $this->travelTo('2026-11-24 12:00:00');
        $first = null;

        foreach (range(1, 5) as $n) {
            $this->travel(1)->minutes();
            $this->postJson(route('portal.push.store', $slug), pushBody(['endpoint' => "https://fcm.googleapis.com/fcm/send/phone-{$n}"]))->assertNoContent();
            $first ??= PushSubscription::query()->sole()->id;
        }

        expect(PushSubscription::query()->count())->toBe(5);

        $this->travel(1)->minutes();
        $this->postJson(route('portal.push.store', $slug), pushBody(['endpoint' => 'https://fcm.googleapis.com/fcm/send/phone-6']))->assertNoContent();

        expect(PushSubscription::query()->count())->toBe(5)
            ->and(PushSubscription::query()->whereKey($first)->exists())->toBeFalse()
            ->and(PushSubscription::query()->where('endpoint', 'https://fcm.googleapis.com/fcm/send/phone-6')->exists())->toBeTrue();
    });

    it('does not count the phones of other people against the five', function (): void {
        PushSubscription::factory()->count(5)->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id]);

        $this->postJson(route('portal.push.store', asPerson($this->ana->id)), pushBody())->assertNoContent();

        expect(PushSubscription::query()->count())->toBe(6);
    });

    it('refuses an address that is not https or whose host is not a push service', function (string $endpoint): void {
        $slug = asPerson($this->ana->id);

        $this->postJson(route('portal.push.store', $slug), pushBody(['endpoint' => $endpoint]))->assertUnprocessable()->assertJsonValidationErrors('endpoint');

        expect(PushSubscription::query()->count())->toBe(0);
    })->with([
        'http' => 'http://fcm.googleapis.com/fcm/send/x',
        'metadata address' => 'https://169.254.169.254/x',
        'host in the path' => 'https://evil.example/fcm.googleapis.com',
    ]);

    it('takes an address of 2048 characters and refuses one of 2049', function (): void {
        $slug = asPerson($this->ana->id);
        $prefix = 'https://fcm.googleapis.com/';

        $this->postJson(route('portal.push.store', $slug), pushBody(['endpoint' => $prefix.str_repeat('a', 2048 - strlen($prefix))]))->assertNoContent();
        $this->postJson(route('portal.push.store', $slug), pushBody(['endpoint' => $prefix.str_repeat('b', 2049 - strlen($prefix))]))->assertUnprocessable()->assertJsonValidationErrors('endpoint');

        expect(PushSubscription::query()->count())->toBe(1);
    });

    it('checks the size and the alphabet of the keys', function (string $field, string $value): void {
        $slug = asPerson($this->ana->id);
        $keys = ['p256dh' => Str::random(87), 'auth' => Str::random(22), $field => $value];

        $this->postJson(route('portal.push.store', $slug), pushBody(['keys' => $keys]))->assertUnprocessable()->assertJsonValidationErrors("keys.{$field}");

        expect(PushSubscription::query()->count())->toBe(0);
    })->with([
        'p256dh of 86' => ['p256dh', str_repeat('a', 86)],
        'p256dh of 88' => ['p256dh', str_repeat('a', 88)],
        'auth of 21' => ['auth', str_repeat('a', 21)],
        'auth of 23' => ['auth', str_repeat('a', 23)],
        'p256dh outside base64url' => ['p256dh', str_repeat('+', 87)],
        'auth outside base64url' => ['auth', str_repeat('=', 22)],
    ]);

    it('asks for the keys and the address', function (): void {
        $slug = asPerson($this->ana->id);

        $this->postJson(route('portal.push.store', $slug), [])->assertUnprocessable()->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    });
});

describe('the public key', function (): void {
    it('gives the key the phone subscribes with', function (): void {
        $slug = asPerson($this->ana->id);

        $this->getJson(route('portal.push.show', $slug))->assertOk()->assertExactJson(['publicKey' => config('services.vapid.public_key')]);
    });

    it('gives none without the keys or with a wrong subject, so the app does not offer the notices', function (array $override): void {
        config(['services.vapid' => [...config()->array('services.vapid'), ...$override]]);
        $slug = asPerson($this->ana->id);

        $this->getJson(route('portal.push.show', $slug))->assertOk()->assertExactJson(['publicKey' => null]);
    })->with([
        'no public key' => [['public_key' => null]],
        'no private key' => [['private_key' => null]],
        'wrong subject' => [['subject' => 'soporte@humetria.com']],
    ]);
});

describe('giving it up', function (): void {
    it('deletes the subscription of the person', function (): void {
        $mine = PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        $keeps = PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        $slug = asPerson($this->ana->id);

        $this->deleteJson(route('portal.push.destroy', $slug), ['endpoint' => $mine->endpoint])->assertNoContent();

        expect(PushSubscription::query()->whereKey($mine->id)->exists())->toBeFalse()
            ->and(PushSubscription::query()->whereKey($keeps->id)->exists())->toBeTrue();
    });

    it('answers 204 to an address it does not know, changing nothing', function (): void {
        $keeps = PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
        $slug = asPerson($this->ana->id);

        $this->deleteJson(route('portal.push.destroy', $slug), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/unknown'])->assertNoContent();

        expect(PushSubscription::query()->whereKey($keeps->id)->exists())->toBeTrue();
    });

    it('does not delete the phone of another person: the answer is the same and the row stays', function (): void {
        $theirs = PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->beto->id]);
        $slug = asPerson($this->ana->id);

        $this->deleteJson(route('portal.push.destroy', $slug), ['endpoint' => $theirs->endpoint])->assertNoContent();

        expect(PushSubscription::query()->whereKey($theirs->id)->exists())->toBeTrue();
    });

    it('asks for the address', function (): void {
        $slug = asPerson($this->ana->id);

        $this->deleteJson(route('portal.push.destroy', $slug), [])->assertUnprocessable()->assertJsonValidationErrors('endpoint');
    });
});

describe('access', function (): void {
    it('answers 401 in JSON to a visitor and writes nothing', function (): void {
        $this->postJson(route('portal.push.store', 'acme'), pushBody())->assertUnauthorized();
        $this->getJson(route('portal.push.show', 'acme'))->assertUnauthorized();
        $this->deleteJson(route('portal.push.destroy', 'acme'), ['endpoint' => 'https://fcm.googleapis.com/x'])->assertUnauthorized();

        expect(PushSubscription::query()->count())->toBe(0);
    });

    it('answers 404 to a company that does not exist or is blocked', function (): void {
        asPerson($this->ana->id);

        $this->getJson(route('portal.push.show', 'nobody'))->assertNotFound();

        $this->tenant->update(suspension());
        $this->getJson(route('portal.push.show', 'acme'))->assertNotFound();
    });

    it('logs the subscription and giving it up without the address nor the keys', function (): void {
        $path = storage_path('logs/push-routes-'.getmypid().'.log');
        File::delete($path);
        config(['logging.default' => 'single', 'logging.channels.single.path' => $path, 'logging.channels.single.level' => 'debug']);
        Log::forgetChannel('single');
        $slug = asPerson($this->ana->id);
        $body = pushBody();

        $this->postJson(route('portal.push.store', $slug), $body)->assertNoContent();
        $this->deleteJson(route('portal.push.destroy', $slug), ['endpoint' => $body['endpoint']])->assertNoContent();

        $log = File::get($path);
        File::delete($path);
        expect($log)->toContain('"push.subscribed"')->toContain('"push.subscriptions_forgotten"')
            ->not->toContain($body['endpoint'])->not->toContain($body['keys']['p256dh'])->not->toContain($body['keys']['auth'])
            ->not->toContain($this->ana->id);
    });
});
