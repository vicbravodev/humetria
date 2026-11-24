<?php

declare(strict_types=1);

use App\Modules\Attendance\Events\CorrectionResolved;
use App\Modules\Attendance\Events\OfflineMarkReviewed;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Tenancy\Models\Tenant;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\VAPID;

/*
 * A push says nothing: what leaves the server is a request with no body, to an address that names the phone and not the person.
 * What the phone shows is fixed in the app. Nothing of the person is in the request, in the model's array or in the log.
 */
beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Autopartes Zapata', 'slug' => 'zapata']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana Lucía Ramírez']);
    $keys = VAPID::createVapidKeys();
    config(['services.vapid' => ['public_key' => $keys['publicKey'], 'private_key' => $keys['privateKey'], 'subject' => 'mailto:soporte@humetria.com']]);
    $this->phone = PushSubscription::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);
});

it('sends a request with no body for both events, with nothing of the person, the company or the mark', function (): void {
    Http::fake(['*' => Http::response('', 201)]);

    event(new OfflineMarkReviewed('mark-0001', $this->ana->id, 'incorrect'));
    event(new CorrectionResolved('request-0001', $this->ana->id, false));

    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);

    foreach ($requests as [$request]) {
        $headers = json_encode($request->headers(), JSON_THROW_ON_ERROR);
        expect($request->body())->toBe('')
            ->and($headers)->not->toContain('Ana')->not->toContain('Zapata')->not->toContain($this->ana->id)->not->toContain('mark-0001')->not->toContain('request-0001')->not->toContain($this->tenant->id)
            ->and($request->url())->not->toContain($this->ana->id);
    }
});

it('hides the address and the keys of a subscription when it is turned into an array', function (): void {
    expect(array_keys($this->phone->toArray()))->not->toContain('endpoint', 'p256dh', 'auth');
});

it('leaves no address, key, name nor person in the log along a whole cycle: subscribe, send, and a phone that is gone', function (): void {
    $path = storage_path('logs/push-privacy-'.getmypid().'.log');
    File::delete($path);
    config(['logging.default' => 'single', 'logging.channels.single.path' => $path, 'logging.channels.single.level' => 'debug']);
    Log::forgetChannel('single');
    $this->phone->delete();
    $slug = asPerson($this->ana->id);
    $body = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/'.Str::uuid(), 'keys' => ['p256dh' => Str::random(87), 'auth' => Str::random(22)]];

    $this->postJson(route('portal.push.store', $slug), $body)->assertNoContent();
    Http::fake(['*' => Http::sequence()->push('', 201)->push('', 410)]);
    event(new CorrectionResolved('request-0001', $this->ana->id, true));
    event(new CorrectionResolved('request-0002', $this->ana->id, true));

    $log = File::get($path);
    File::delete($path);
    expect($log)->toContain('push.subscribed')->toContain('push.sent')->toContain('push.expired');

    foreach ([$body['endpoint'], $body['keys']['p256dh'], $body['keys']['auth'], $this->phone->endpoint, 'Ana Lucía', $this->ana->id, config('services.vapid.private_key')] as $secret) {
        expect($log)->not->toContain($secret);
    }
});

it('is not a request the page can fill: what the phone shows does not come from the server', function (): void {
    Http::fake(['*' => Http::response('', 201)]);

    event(new CorrectionResolved('request-0001', $this->ana->id, true));

    Http::assertSent(fn (Request $request): bool => $request->body() === '' && $request->header('Content-Type') !== ['application/json']);
});
