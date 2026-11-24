<?php

declare(strict_types=1);

use App\Modules\Notifications\Actions\SendPush;
use App\Modules\Notifications\Exceptions\PushRateLimited;
use App\Modules\Notifications\Exceptions\PushUnavailable;
use App\Modules\Notifications\Jobs\DeliverPush;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Notifications\Support\VapidConfig;
use App\Modules\Notifications\Support\WebPushFactory;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;
use Psr\Log\NullLogger;

mutates(SendPush::class, DeliverPush::class, WebPushFactory::class);

/*
 * The notice itself: one POST with no body to the push service of the phone. The HTTP client of Laravel is faked, never the network.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-24 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->keys = VAPID::createVapidKeys();
    config(['services.vapid' => ['public_key' => $this->keys['publicKey'], 'private_key' => $this->keys['privateKey'], 'subject' => 'mailto:soporte@humetria.com']]);
    $this->phone = PushSubscription::factory()->create(['tenant_id' => $this->tenant->id]);
});

/** Runs the job as the queue does, with its interactions faked. */
function deliver(PushSubscription $phone): DeliverPush
{
    $job = new DeliverPush($phone->tenant_id, $phone->id);
    $job->withFakeQueueInteractions();
    $job->handle(resolve(Companies::class), resolve(SendPush::class));

    return $job;
}

describe('a notice that arrives (201)', function (): void {
    it('posts once to the address of the phone, with no body and the headers of a notice without content', function (): void {
        Http::fake(['*' => Http::response('', 201)]);

        resolve(SendPush::class)->handle($this->phone->id);

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $authorization = $request->header('Authorization')[0] ?? '';

            return $request->method() === 'POST'
                && $request->url() === $this->phone->endpoint
                && $request->body() === ''
                && $request->header('Content-Length') === ['0']
                && $request->header('Content-Encoding') === []
                && $request->header('TTL') === ['3600']
                && $request->header('Urgency') === ['normal']
                && str_starts_with($authorization, 'vapid t=')
                && str_contains($authorization, ', k='.$this->keys['publicKey']);
        });
    });

    it('notes when it arrived and logs it with the id of the subscription only', function (): void {
        Http::fake(['*' => Http::response('', 201)]);
        Log::spy();

        resolve(SendPush::class)->handle($this->phone->id);

        expect($this->phone->refresh()->last_success_at?->toIso8601String())->toBe('2026-11-24T15:00:00+00:00');
        Log::shouldHaveReceived('info')->with('push.sent', ['subscription_id' => $this->phone->id])->once();
    });
});

describe('a phone that is gone (404 and 410)', function (): void {
    it('deletes the subscription and says so', function (int $status): void {
        Http::fake(['*' => Http::response('', $status)]);
        Log::spy();

        resolve(SendPush::class)->handle($this->phone->id);

        expect(PushSubscription::query()->count())->toBe(0);
        Log::shouldHaveReceived('info')->with('push.expired', ['subscription_id' => $this->phone->id, 'status' => $status])->once();
    })->with([404, 410]);
});

describe('a push service that says to wait (429)', function (): void {
    it('releases the job for the seconds the service says', function (): void {
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => '120'])]);

        deliver($this->phone)->assertReleased(120);
    });

    it('reads the wait as an HTTP date too', function (): void {
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => CarbonImmutable::now()->addSeconds(90)->toRfc7231String()])]);

        deliver($this->phone)->assertReleased(90);
    });

    it('waits 60 seconds when the service does not say, and keeps the wait between 1 and 3600', function (?string $header, int $released): void {
        Http::fake(['*' => Http::response('', 429, $header === null ? [] : ['Retry-After' => $header])]);

        deliver($this->phone)->assertReleased($released);
    })->with([
        'no header' => [null, 60],
        'too long' => ['99999', 3600],
        'exactly an hour' => ['3600', 3600],
        'zero' => ['0', 1],
        'negative' => ['-5', 1],
        'a date in the past' => ['Sat, 01 Jan 2000 00:00:00 GMT', 1],
        'nonsense' => ['soon', 60],
    ]);

    it('keeps the subscription', function (): void {
        Http::fake(['*' => Http::response('', 429)]);

        deliver($this->phone);

        expect(PushSubscription::query()->count())->toBe(1);
    });

    it('throws what the job catches', function (): void {
        Http::fake(['*' => Http::response('', 429, ['Retry-After' => '7'])]);

        expect(fn () => resolve(SendPush::class)->handle($this->phone->id))->toThrow(PushRateLimited::class);
    });
});

describe('a push service that fails (5xx or no answer)', function (): void {
    it('throws so the queue tries again later, keeping the subscription', function (): void {
        Http::fake(['*' => Http::response('', 503)]);

        expect(fn () => resolve(SendPush::class)->handle($this->phone->id))->toThrow(PushUnavailable::class)
            ->and(PushSubscription::query()->count())->toBe(1);
    });

    it('throws the same when the connection fails, and the job fails to be tried again', function (): void {
        Http::fake(fn () => throw new ConnectionException('down'));

        expect(fn () => deliver($this->phone))->toThrow(PushUnavailable::class);
    });
});

describe('a notice the service refuses (other 4xx)', function (): void {
    it('logs it with the status, does not retry and does not delete: a wrong key would empty everyone', function (int $status): void {
        Http::fake(['*' => Http::response('', $status)]);
        Log::spy();

        resolve(SendPush::class)->handle($this->phone->id);

        expect(PushSubscription::query()->count())->toBe(1);
        Log::shouldHaveReceived('warning')->with('push.failed', ['subscription_id' => $this->phone->id, 'status' => $status])->once();
    })->with([400, 403, 413]);
});

describe('nothing to send', function (): void {
    it('makes no request without the subscription, without keys or for a blocked company', function (string $case): void {
        Http::fake();
        $phone = $this->phone;

        match ($case) {
            'deleted' => $phone->delete(),
            'no keys' => config(['services.vapid.private_key' => null]),
            'blocked' => $this->tenant->update(suspension()),
        };

        deliver($phone);

        Http::assertNothingSent();
    })->with(['deleted', 'no keys', 'blocked']);

    it('does not retry a wrong key: it logs it and sends nothing', function (): void {
        config(['services.vapid.private_key' => 'not a key']);
        Http::fake();
        Log::spy();

        resolve(SendPush::class)->handle($this->phone->id);

        Http::assertNothingSent();
        Log::shouldHaveReceived('warning')->with('push.failed', ['subscription_id' => $this->phone->id, 'reason' => 'invalid_keys'])->once();
    });
});

describe('the job', function (): void {
    it('is tried 3 times, a minute and five minutes apart, and says when it gave up', function (): void {
        $job = new DeliverPush($this->tenant->id, $this->phone->id);
        Log::spy();

        $job->failed(new RuntimeException('x'));

        expect($job->tries)->toBe(3)->and($job->backoff)->toBe([60, 300]);
        Log::shouldHaveReceived('warning')->with('push.failed', ['subscription_id' => $this->phone->id, 'reason' => 'exhausted'])->once();
    });

    it('carries only ids on the queue', function (): void {
        expect(serialize(new DeliverPush($this->tenant->id, $this->phone->id)))->not->toContain($this->phone->endpoint)->not->toContain($this->phone->employee_id);
    });
});

describe('the library', function (): void {
    it('is built with a logger, so a missing extension does not become an exception', function (): void {
        $vapid = VapidConfig::fromConfig();
        $webPush = resolve(WebPushFactory::class)->make($vapid ?? throw new RuntimeException('keys'));

        expect($webPush)->toBeInstanceOf(WebPush::class)
            ->and((new ReflectionProperty(WebPush::class, 'logger'))->getValue($webPush))->toBeInstanceOf(NullLogger::class)
            ->and($webPush->getDefaultOptions())->toMatchArray(['TTL' => 3600, 'urgency' => 'normal']);
    });

    it('logs nothing of the address nor of the keys while sending', function (): void {
        $path = storage_path('logs/send-push-'.getmypid().'.log');
        File::delete($path);
        config(['logging.default' => 'single', 'logging.channels.single.path' => $path, 'logging.channels.single.level' => 'debug']);
        Log::forgetChannel('single');
        Http::fake(['*' => Http::response('', 201)]);

        resolve(SendPush::class)->handle($this->phone->id);

        $log = File::get($path);
        File::delete($path);
        expect($log)->toContain('push.sent')->not->toContain($this->phone->endpoint)->not->toContain($this->phone->p256dh)->not->toContain($this->phone->auth)
            ->not->toContain($this->phone->employee_id)->not->toContain($this->keys['privateKey']);
    });
});
