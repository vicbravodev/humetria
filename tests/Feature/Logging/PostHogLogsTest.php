<?php

declare(strict_types=1);

use App\Logging\PostHogLogs;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

mutates(PostHogLogs::class);

const LOGS_TOKEN = 'phc_AbCdEfGhIjKlMnOpQrStUvWxYz0123456789ABCDEFG';
const LOGS_URL = 'https://us.i.posthog.com/i/v1/logs';

function shippingLogs(string $token = LOGS_TOKEN): void
{
    config(['services.posthog.project_token' => $token, 'logging.channels.posthog.level' => 'info']);
    Log::forgetChannel('posthog');
    Http::fake([LOGS_URL => Http::response([], 200)]);
}

/** @return list<Request> */
function logBatches(): array
{
    return array_values(Http::recorded()->map(fn (array $pair): Request => $pair[0])->filter(fn (Request $request): bool => $request->url() === LOGS_URL)->all());
}

/** @return list<array<string, mixed>> */
function shippedRecords(): array
{
    return collect(logBatches())->flatMap(fn (Request $request): array => $request->data()['resourceLogs'][0]['scopeLogs'][0]['logRecords'])->values()->all();
}

/**
 * @param  array<string, mixed>  $record
 * @return array<string, mixed>
 */
function attributesOf(array $record): array
{
    return collect($record['attributes'])->mapWithKeys(fn (array $attribute): array => [$attribute['key'] => array_values($attribute['value'])[0]])->all();
}

it('ships a batch of logs to PostHog as OpenTelemetry, once, when flushed', function (): void {
    shippingLogs();

    Log::channel('posthog')->info('campaign.launched', ['campaign_id' => 'c-1', 'sent' => 3, 'urgent' => false]);
    Log::channel('posthog')->warning('queue.long_wait', ['queue' => 'default']);

    expect(logBatches())->toBe([]);

    PostHogLogs::flush();

    $batches = logBatches();
    expect($batches)->toHaveCount(1)
        ->and($batches[0]->hasHeader('Authorization', 'Bearer '.LOGS_TOKEN))->toBeTrue()
        ->and($batches[0]->data()['resourceLogs'][0]['resource']['attributes'])->toContain(
            ['key' => 'service.name', 'value' => ['stringValue' => 'humetria']],
            ['key' => 'deployment.environment', 'value' => ['stringValue' => 'testing']],
        );

    $records = shippedRecords();
    expect($records)->toHaveCount(2)
        ->and($records[0]['body'])->toBe(['stringValue' => 'campaign.launched'])
        ->and($records[0]['severityText'])->toBe('INFO')
        ->and($records[0]['severityNumber'])->toBe(9)
        ->and($records[0]['timeUnixNano'])->toMatch('/^\d{19}$/')
        ->and(attributesOf($records[0]))->toMatchArray(['campaign_id' => 'c-1', 'sent' => '3', 'urgent' => false])
        ->and($records[1]['severityText'])->toBe('WARN')
        ->and($records[1]['severityNumber'])->toBe(13);

    PostHogLogs::flush();
    expect(logBatches())->toHaveCount(1);
});

it('redacts the sensitive keys before they leave', function (): void {
    shippingLogs();

    Log::channel('posthog')->info('survey.submitted', ['answers' => [1 => 'siempre'], 'nested' => ['password' => 'hunter2']]);
    PostHogLogs::flush();

    $body = json_encode(logBatches()[0]->data(), JSON_THROW_ON_ERROR);
    expect($body)->not->toContain('siempre')->not->toContain('hunter2')->toContain('[REDACTED]');
});

it('sends the class and place of an exception, never its words', function (): void {
    shippingLogs();

    Log::channel('posthog')->error('no se pudo enviar a ana@empresa.mx', ['exception' => new RuntimeException('no se pudo enviar a ana@empresa.mx')]);
    PostHogLogs::flush();

    $record = shippedRecords()[0];
    expect($record['body'])->toBe(['stringValue' => 'RuntimeException'])
        ->and(attributesOf($record))->toMatchArray(['exception.type' => 'RuntimeException', 'exception.file' => 'tests/Feature/Logging/PostHogLogsTest.php'])
        ->and(json_encode($record, JSON_THROW_ON_ERROR))->not->toContain('ana@empresa.mx');
});

it('leaves out what is below its level', function (): void {
    shippingLogs();

    Log::channel('posthog')->debug('noise');
    PostHogLogs::flush();

    expect(logBatches())->toBe([]);
});

it('sends nothing without a token', function (): void {
    shippingLogs('');

    Log::channel('posthog')->info('campaign.launched');
    PostHogLogs::flush();

    expect(logBatches())->toBe([]);
});

it('swallows a PostHog that is down or refuses: logging never breaks what logs', function (Closure $response): void {
    shippingLogs();
    Http::fake([LOGS_URL => $response]);

    Log::channel('posthog')->info('campaign.launched');

    expect(PostHogLogs::flush(...))->not->toThrow(Throwable::class);
})->with([
    'down' => [fn () => fn () => throw new ConnectionException('down')],
    'refused' => [fn () => Http::response('nope', 500)],
]);

it('flushes at the end of every request', function (): void {
    shippingLogs();
    Route::middleware('web')->get('/_logs', function (): string {
        Log::channel('posthog')->info('page.seen');

        return 'ok';
    });

    $this->get('/_logs')->assertOk();

    expect(shippedRecords())->toHaveCount(1);
});

it('flushes after every queued job', function (): void {
    shippingLogs();

    dispatch(fn () => Log::channel('posthog')->info('job.ran'));

    expect(shippedRecords())->toHaveCount(1);
});

it('flushes on its own once the buffer is full', function (): void {
    shippingLogs();

    foreach (range(1, 100) as $n) {
        Log::channel('posthog')->info('bulk.line', ['n' => $n]);
    }

    expect(logBatches())->toHaveCount(1)
        ->and(shippedRecords())->toHaveCount(100);
});
