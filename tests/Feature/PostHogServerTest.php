<?php

declare(strict_types=1);

use App\Http\Analytics;
use App\Support\PostHogExceptions;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

mutates(Analytics::class, PostHogExceptions::class);

const SERVER_TOKEN = 'phc_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG';
const CAPTURE_URL = 'https://us.i.posthog.com/i/v0/e/';

function serverMeasuring(string $token = SERVER_TOKEN, string $consent = 'granted'): void
{
    config(['services.posthog.project_token' => $token, 'services.posthog.consent_default' => $consent]);
    Http::fake([CAPTURE_URL => Http::response(['status' => 1])]);
}

/** @return list<array<string, mixed>> The bodies PostHog received. */
function sentToPostHog(): array
{
    return Http::recorded()
        ->filter(fn (array $pair): bool => $pair[0]->url() === CAPTURE_URL)
        ->map(fn (array $pair): array => $pair[0]->data())
        ->values()
        ->all();
}

/** @return array<string, mixed> */
function submittedDemo(): array
{
    return [
        'name' => 'Ana López',
        'email' => 'ana@empresa.mx',
        'whatsapp' => '55 1234 5678',
        'company' => 'Empresa Ejemplo',
        'profile' => 'consultant',
        'workforce' => '51_100',
        'tools' => ['nom_035', 'ley_silla'],
        'message' => 'Queremos aplicar la Guía III este año.',
        'form_extra' => '',
    ];
}

function throwingRoute(Throwable $exception): void
{
    Route::middleware('web')->get('/_boom', fn () => throw $exception)->name('boom');
}

describe('the demo request, seen from the server', function (): void {
    it('tells PostHog a request was stored, with the profile and the size and nothing about the person', function (): void {
        serverMeasuring();

        $this->post(route('demo-requests.store'), submittedDemo())->assertRedirect();

        $sent = sentToPostHog();
        expect($sent)->toHaveCount(1)
            ->and($sent[0]['api_key'])->toBe(SERVER_TOKEN)
            ->and($sent[0]['event'])->toBe('demo_request_stored')
            ->and($sent[0]['distinct_id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
            ->and($sent[0]['properties'])->toMatchArray([
                'profile' => 'consultant',
                'workforce' => '51_100',
                'tools_count' => 2,
                '$process_person_profile' => false,
                '$lib' => 'humetria-php',
            ]);

        $body = json_encode($sent[0], JSON_THROW_ON_ERROR);
        foreach (['Ana', 'López', 'ana@empresa.mx', '5512345678', 'Empresa Ejemplo', 'Guía III'] as $personal) {
            expect($body)->not->toContain($personal);
        }
    });

    it('does not tell PostHog without a token, without consent or for a bot', function (Closure $setUp): void {
        serverMeasuring();
        $setUp();

        $this->post(route('demo-requests.store'), submittedDemo())->assertRedirect();

        expect(sentToPostHog())->toBe([]);
    })->with([
        'no token' => [fn () => config(['services.posthog.project_token' => ''])],
        'consent denied' => [fn () => config(['services.posthog.consent_default' => 'denied'])],
    ]);

    it('says nothing for a filled honeypot: that was not a person', function (): void {
        serverMeasuring();

        $this->post(route('demo-requests.store'), ['form_extra' => 'bot'] + submittedDemo())->assertRedirect();

        expect(sentToPostHog())->toBe([]);
    });

    it('stores the request and answers the same when PostHog is down', function (): void {
        serverMeasuring();
        Http::fake([CAPTURE_URL => fn () => throw new ConnectionException('cURL error 28 for ana@empresa.mx')]);
        Log::spy();

        $this->post(route('demo-requests.store'), submittedDemo())->assertRedirect();

        $this->assertDatabaseCount('demo_requests', 1);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'posthog.send_failed'
            && $context === ['exception' => 'ConnectionException'])->once();
    });

    it('stores the request and answers the same when PostHog refuses the event', function (): void {
        serverMeasuring();
        Http::fake([CAPTURE_URL => Http::response('nope', 500)]);

        $this->post(route('demo-requests.store'), submittedDemo())->assertRedirect();

        $this->assertDatabaseCount('demo_requests', 1);
    });
});

describe('the errors of the server', function (): void {
    it('sends an uncaught exception as a PostHog exception with its trace and none of its words', function (): void {
        serverMeasuring();
        throwingRoute(new RuntimeException('no se pudo enviar a ana@empresa.mx'));

        $this->get('/_boom')->assertServerError();

        $sent = sentToPostHog();
        expect($sent)->toHaveCount(1)
            ->and($sent[0]['event'])->toBe('$exception')
            ->and($sent[0]['properties']['$exception_list'][0]['type'])->toBe('RuntimeException')
            ->and($sent[0]['properties']['$exception_list'][0]['value'])->toBe('')
            ->and($sent[0]['properties'])->toMatchArray([
                'route' => 'boom',
                '$process_person_profile' => false,
                '$exception_level' => 'error',
            ])
            ->and($sent[0]['properties']['request_id'])->toBeString()->not->toBe('');

        expect(json_encode($sent[0], JSON_THROW_ON_ERROR))->not->toContain('ana@empresa.mx');
    });

    it('lists the frames of the trace oldest first, with paths inside the project and no arguments', function (): void {
        serverMeasuring();
        throwingRoute(new RuntimeException('x'));

        $this->get('/_boom');

        $frames = sentToPostHog()[0]['properties']['$exception_list'][0]['stacktrace']['frames'];
        $last = end($frames);

        expect($frames)->not->toBeEmpty()
            ->and($last['filename'])->toBe('tests/Feature/PostHogServerTest.php')
            ->and($last['in_app'])->toBeTrue()
            ->and($last)->not->toHaveKey('vars')
            ->and(collect($frames)->pluck('filename')->filter(fn (string $file): bool => str_starts_with($file, '/'))->all())->toBe([])
            ->and(collect($frames)->firstWhere(fn (array $frame): bool => str_starts_with($frame['filename'], 'vendor/'))['in_app'] ?? false)->toBeFalse();
    });

    it('reports a failed query by its class, never with its bindings', function (): void {
        serverMeasuring();
        throwingRoute(new QueryException('pgsql', 'select * from users where email = ?', ['ana@empresa.mx'], new Exception('boom')));

        $this->get('/_boom');

        $sent = sentToPostHog();
        expect($sent)->toHaveCount(1)
            ->and($sent[0]['properties']['$exception_list'][0]['type'])->toBe('Illuminate\\Database\\QueryException')
            ->and(json_encode($sent[0], JSON_THROW_ON_ERROR))->not->toContain('ana@empresa.mx');
    });

    it('reports errors even while the landing consent is denied: it holds no data of any visitor', function (): void {
        serverMeasuring(consent: 'denied');
        throwingRoute(new RuntimeException('x'));

        $this->get('/_boom');

        expect(sentToPostHog())->toHaveCount(1);
    });

    it('sends nothing without a token', function (): void {
        serverMeasuring('');
        throwingRoute(new RuntimeException('x'));

        $this->get('/_boom')->assertServerError();

        expect(sentToPostHog())->toBe([]);
    });

    it('does not report what Laravel does not report: a missing page, a failed validation', function (): void {
        serverMeasuring();

        $this->get('/no-such-page')->assertNotFound();
        $this->post(route('demo-requests.store'), [])->assertSessionHasErrors();

        expect(sentToPostHog())->toBe([]);
    });

    it('still logs the error as before', function (): void {
        serverMeasuring();
        throwingRoute(new RuntimeException('visible-in-our-log'));
        Log::spy();

        $this->get('/_boom');

        Log::shouldHaveReceived('error')->withArgs(fn (string $message): bool => str_contains($message, 'visible-in-our-log'))->atLeast()->once();
    });

    it('keeps answering when PostHog is down: a failing report never becomes a second error', function (): void {
        serverMeasuring();
        Http::fake([CAPTURE_URL => fn () => throw new ConnectionException('down')]);
        throwingRoute(new RuntimeException('x'));
        Log::spy();

        $this->get('/_boom')->assertServerError();

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => $message === 'posthog.send_failed')->once();
    });

    it('reports what the code reports by hand too (a job that failed, a caught exception)', function (): void {
        serverMeasuring();

        report(new LogicException('from a job'));

        expect(sentToPostHog())->toHaveCount(1)
            ->and(sentToPostHog()[0]['properties']['$exception_list'][0]['type'])->toBe('LogicException');
    });
});
