<?php

declare(strict_types=1);

use App\Http\SsrPages;
use App\Listeners\LogSsrRenderFailure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

mutates(LogSsrRenderFailure::class);

beforeEach(function (): void {
    $this->logPath = storage_path('logs/ssr-degradation-test.log');
    File::delete($this->logPath);
    config([
        'logging.channels.single.path' => $this->logPath,
        'inertia.ssr.enabled' => true,
        'inertia.ssr.ensure_bundle_exists' => false,
    ]);
    Log::forgetChannel('single');
    Log::forgetChannel('stack');
});

afterEach(function (): void {
    File::delete($this->logPath);
});

/** Node answers with the error the SSR server sends when a render throws. */
function nodeFails(string $type = 'render', string $error = 'boom', string $stack = 'at render', ?string $hint = 'Look at X'): void
{
    Http::fake(['http://127.0.0.1:13714/render' => Http::response(
        ['error' => $error, 'type' => $type, 'stack' => $stack, 'hint' => $hint],
        500,
    )]);
}

/** Node is not there: the connection is refused (a Node that hangs past the timeout ends the same way). */
function nodeIsDown(): void
{
    // Counted by hand: a request that fails to connect leaves no response for Http::recorded() to keep.
    Http::fake(['http://127.0.0.1:13714/render' => function (): never {
        Cache::increment('test:ssr-attempts');

        throw new ConnectionException('cURL error 7: Connection refused');
    }]);
}

function nodeAttempts(): int
{
    return Cache::get('test:ssr-attempts', 0);
}

/** @return list<array<string, mixed>> every ssr.render_failed line, decoded */
function failureLines(): array
{
    $lines = [];

    foreach (explode("\n", trim(File::exists(test()->logPath) ? File::get(test()->logPath) : '')) as $line) {
        $decoded = $line === '' ? null : json_decode($line, true, flags: JSON_THROW_ON_ERROR);

        if (is_array($decoded) && ($decoded['message'] ?? null) === 'ssr.render_failed') {
            $lines[] = $decoded;
        }
    }

    return $lines;
}

it('serves the landing without the render when Node answers with an error', function (): void {
    nodeFails();

    $this->get('/')->assertOk()->assertSee('data-page', false);
});

it('serves the landing without the render when Node is down', function (): void {
    nodeIsDown();

    $this->get('/')->assertOk()->assertSee('data-page', false);
});

it('logs one ssr.render_failed line with the type, component, path and hint', function (): void {
    nodeFails('render', hint: 'Look at X');

    $this->get('/')->assertOk();

    $lines = failureLines();

    expect($lines)->toHaveCount(1)
        ->and($lines[0])->toHaveKey('context.type', 'render')
        ->toHaveKey('context.component', 'landing')
        ->toHaveKey('context.path', '/')
        ->toHaveKey('context.hint', 'Look at X');
});

it('never writes the page, the stack, the error text or the query string', function (): void {
    nodeFails('render', error: 'Cannot read secret-prop', stack: 'at secret-stack (file.js:1)');

    $this->get('/?utm_source=secret-campaign')->assertOk();

    $log = File::get($this->logPath);

    expect(failureLines())->toHaveCount(1)
        ->and($log)->not->toContain('secret-prop')
        ->not->toContain('secret-stack')
        ->not->toContain('secret-campaign')
        ->not->toContain('"page"')
        ->not->toContain('"stack"')
        ->not->toContain('"error"');
});

it('writes at most one line per type and minute, however many visits fail', function (): void {
    nodeFails();

    foreach (range(1, 100) as $visit) {
        $this->get('/')->assertOk();
    }

    expect(failureLines())->toHaveCount(1)
        ->and(Cache::get('ssr:failed:count'))->toBe(99);
});

it('writes another line when the minute has passed', function (): void {
    nodeFails();

    $this->get('/')->assertOk();
    $this->travel(59)->seconds();
    $this->get('/')->assertOk();

    expect(failureLines())->toHaveCount(1);

    $this->travel(2)->seconds();
    $this->get('/')->assertOk();

    expect(failureLines())->toHaveCount(2);
});

it('keeps a line of its own for each type', function (): void {
    Http::fake(['http://127.0.0.1:13714/render' => Http::sequence()
        ->push(['error' => 'a', 'type' => 'render'], 500)
        ->push(['error' => 'b', 'type' => 'browser-api'], 500)]);

    $this->get('/')->assertOk();
    $this->get('/')->assertOk();

    expect(array_column(array_column(failureLines(), 'context'), 'type'))->toBe(['render', 'browser-api']);
});

it('does not open the circuit breaker for a render error', function (): void {
    nodeFails('render');

    $this->get('/')->assertOk();

    expect(Cache::has(SsrPages::CIRCUIT_KEY))->toBeFalse();
});

it('opens the circuit breaker when Node cannot be reached, so the next visit does not try', function (): void {
    nodeIsDown();

    $this->get('/')->assertOk();

    expect(Cache::has(SsrPages::CIRCUIT_KEY))->toBeTrue()
        ->and(failureLines())->toHaveCount(1)
        ->and(failureLines()[0])->toHaveKey('context.type', 'connection');

    $this->get('/')->assertOk();

    expect(nodeAttempts())->toBe(1);
});

it('opens the circuit breaker even when the line is not written', function (): void {
    nodeIsDown();
    Cache::put('ssr:failed:connection', 1, 60);

    $this->get('/')->assertOk();

    expect(Cache::has(SsrPages::CIRCUIT_KEY))->toBeTrue()
        ->and(failureLines())->toBe([]);
});

it('tries Node again once the circuit breaker has closed, after thirty seconds', function (): void {
    nodeIsDown();

    $this->get('/')->assertOk();
    $this->travel(29)->seconds();
    $this->get('/')->assertOk();

    expect(nodeAttempts())->toBe(1);

    $this->travel(2)->seconds();
    $this->get('/')->assertOk();

    expect(nodeAttempts())->toBe(2);
});
