<?php

declare(strict_types=1);

namespace App\Logging;

use App\Http\Analytics;
use Illuminate\Support\Facades\Http;
use JsonSerializable;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;
use WeakMap;

/**
 * The `posthog` log channel: PostHog Logs (OpenTelemetry over HTTP, JSON), so its alerts can read what the app logs.
 * Records wait in memory and leave in one request when the request, job or command ends (or every BATCH records),
 * never through the queue: a Valkey that is down must not silence the logs that say so. A PostHog that is slow or down
 * costs at most the timeout and loses that batch; it never throws. The redaction every channel gets runs before this.
 * An exception goes as its class and place, never its message (as PostHogExceptions does).
 */
final class PostHogLogs extends AbstractProcessingHandler
{
    public const string URL = Analytics::HOST.'/i/v1/logs';

    private const int BATCH = 100;

    /** @var list<array<string, mixed>> */
    private array $buffer = [];

    /**
     * Every handler alive, so flush finds them without the log facade (a test may mock it).
     *
     * @var WeakMap<self, true>|null
     */
    private static ?WeakMap $open = null;

    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        self::$open ??= new WeakMap;
        self::$open[$this] = true;
    }

    /** Sends what every open channel holds: called when a request, a job or a command ends. */
    public static function flush(): void
    {
        foreach (self::$open ?? [] as $handler => $_) {
            $handler->send();
        }
    }

    public function send(): void
    {
        $records = $this->buffer;
        $this->buffer = [];
        $token = Analytics::serverToken();

        if ($records === [] || $token === null) {
            return;
        }

        try {
            Http::timeout(3)->connectTimeout(2)->withToken($token)->asJson()->post(self::URL, ['resourceLogs' => [[
                'resource' => ['attributes' => self::attributes(['service.name' => 'humetria', 'deployment.environment' => app()->environment()])],
                'scopeLogs' => [['scope' => ['name' => 'humetria'], 'logRecords' => $records]],
            ]]]);
        } catch (Throwable) {
            // Nowhere to say it without a loop: the same line is already on stderr.
        }
    }

    protected function write(LogRecord $record): void
    {
        $exception = $record->context['exception'] ?? null;
        $values = [...$record->extra, ...$record->context];
        unset($values['exception']);

        if ($exception instanceof Throwable) {
            $values = [...$values, 'exception.type' => $exception::class, 'exception.file' => self::relative($exception->getFile()), 'exception.line' => $exception->getLine()];
        }

        $this->buffer[] = [
            'timeUnixNano' => $record->datetime->format('Uu').'000',
            'severityNumber' => self::severity($record->level)[0],
            'severityText' => self::severity($record->level)[1],
            'body' => ['stringValue' => $exception instanceof Throwable ? $exception::class : $record->message],
            'attributes' => self::attributes($values),
        ];

        if (count($this->buffer) >= self::BATCH) {
            $this->send();
        }
    }

    /** @return array{int, string} The OpenTelemetry severity number and text of a level. */
    private static function severity(Level $level): array
    {
        return match ($level) {
            Level::Debug => [5, 'DEBUG'],
            Level::Info => [9, 'INFO'],
            Level::Notice => [10, 'INFO2'],
            Level::Warning => [13, 'WARN'],
            Level::Error => [17, 'ERROR'],
            Level::Critical => [18, 'ERROR2'],
            Level::Alert => [19, 'ERROR3'],
            Level::Emergency => [21, 'FATAL'],
        };
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<array{key: string, value: array<string, mixed>}>
     */
    private static function attributes(array $values): array
    {
        $attributes = [];

        foreach ($values as $key => $value) {
            $typed = match (true) {
                $value === null => null,
                is_bool($value) => ['boolValue' => $value],
                // OpenTelemetry's JSON carries a 64-bit integer as a string.
                is_int($value) => ['intValue' => (string) $value],
                is_float($value) => ['doubleValue' => $value],
                is_string($value) => ['stringValue' => $value],
                is_array($value) || $value instanceof JsonSerializable => ['stringValue' => (string) json_encode($value, JSON_PARTIAL_OUTPUT_ON_ERROR)],
                default => ['stringValue' => get_debug_type($value)],
            };

            if ($typed !== null) {
                $attributes[] = ['key' => (string) $key, 'value' => $typed];
            }
        }

        return $attributes;
    }

    private static function relative(string $file): string
    {
        return str_starts_with($file, base_path().'/') ? substr($file, strlen(base_path()) + 1) : $file;
    }
}
