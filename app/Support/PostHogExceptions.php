<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Analytics;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Throwable;

/**
 * What PostHog's error tracking is told about an exception of the server: where it happened (class, file, line, trace
 * paths inside the project) and nothing it said. An exception's words often quote the data it choked on (an address, a
 * name, an answer), and a trace's arguments hold it too, so neither leaves. Not tied to a person: no user, no URL.
 */
final class PostHogExceptions
{
    private const int MAX_FRAMES = 50;

    /** How many exceptions of one class reach PostHog a minute: a storm of the same error is one alert, not a bill. */
    private const int PER_CLASS_PER_MINUTE = 10;

    /**
     * Called for every exception Laravel decides to report, before it is logged; without a token it does nothing. It
     * never throws: Laravel runs it without a guard, so a Valkey that is down would otherwise keep the original exception
     * out of the log.
     */
    public static function report(Throwable $exception): void
    {
        if (Analytics::serverToken() === null) {
            return;
        }

        try {
            RateLimiter::attempt(
                'posthog-exception:'.$exception::class,
                self::PER_CLASS_PER_MINUTE,
                fn () => SendPostHogEvent::safely('$exception', (string) Str::uuid(), self::properties($exception)),
            );
        } catch (Throwable $failure) {
            Log::warning('posthog.dispatch_failed', ['exception' => class_basename($failure)]);
        }
    }

    /** @return array<string, mixed> */
    public static function properties(Throwable $exception): array
    {
        $route = app()->bound('request') ? request()->route()?->getName() : null;

        return [
            '$exception_list' => [[
                'type' => $exception::class,
                'value' => '',
                'mechanism' => ['handled' => false, 'synthetic' => false],
                'stacktrace' => ['type' => 'raw', 'frames' => self::frames($exception)],
            ]],
            '$exception_level' => 'error',
            '$process_person_profile' => false,
            '$lib' => 'humetria-php',
            'environment' => app()->environment(),
            'request_id' => Context::get('request_id'),
            'route' => is_string($route) ? $route : null,
        ];
    }

    /**
     * PostHog reads a trace oldest first, the innermost call last: PHP lists it the other way, and its own first
     * frame is the line that threw.
     *
     * @return list<array<string, mixed>>
     */
    private static function frames(Throwable $exception): array
    {
        $frames = [self::frame($exception->getFile(), $exception->getLine(), null)];

        foreach ($exception->getTrace() as $call) {
            $frames[] = self::frame($call['file'] ?? '', $call['line'] ?? 0, ($call['class'] ?? '').($call['type'] ?? '').$call['function']);
        }

        return array_reverse(array_slice($frames, 0, self::MAX_FRAMES));
    }

    /** @return array<string, mixed> */
    private static function frame(string $file, int $line, ?string $function): array
    {
        $path = str_starts_with($file, base_path().'/') ? substr($file, strlen(base_path()) + 1) : $file;

        return [
            'filename' => $path,
            'lineno' => $line,
            'function' => $function ?? '',
            'in_app' => $path !== '' && ! str_starts_with($path, 'vendor/'),
            'platform' => 'custom',
            'lang' => 'php',
        ];
    }
}
