<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Monolog\Level;
use Monolog\Logger as Monolog;
use Monolog\LogRecord;

/**
 * Every configured channel, so a channel added tomorrow is covered without touching this test.
 * "emergency" is not a channel: it is only the path of Laravel's failsafe logger.
 *
 * @return array<int, string>
 */
function configuredLogChannels(): array
{
    return array_values(array_filter(
        array_map(strval(...), array_keys(Config::array('logging.channels'))),
        fn (string $name): bool => $name !== 'emergency',
    ));
}

it('redacts sensitive keys in every log channel', function (): void {
    // Channels that need credentials to be built get harmless ones: nothing is sent, the record never reaches a handler.
    config([
        'logging.channels.slack.url' => 'https://hooks.slack.test/x',
        'logging.channels.papertrail.handler_with.host' => '127.0.0.1',
        'logging.channels.papertrail.handler_with.port' => 514,
    ]);

    $channels = configuredLogChannels();
    expect($channels)->toContain('stack', 'single', 'daily', 'monthly', 'slack', 'papertrail', 'stderr', 'syslog', 'errorlog', 'null');

    foreach ($channels as $name) {
        Log::forgetChannel($name);
        $monolog = Log::channel($name)->getLogger();
        expect($monolog)->toBeInstanceOf(Monolog::class);

        $record = new LogRecord(new DateTimeImmutable, $name, Level::Info, 'survey.submitted', [
            'answers' => [1 => 'siempre'],
            'nested' => ['password' => 'hunter2'],
        ]);

        foreach ($monolog instanceof Monolog ? $monolog->getProcessors() : [] as $processor) {
            $record = $processor($record);
        }

        expect($record->context['answers'] ?? null)->toBe('[REDACTED]', "channel [{$name}] logs answers")
            ->and($record->context['nested'] ?? null)->toBe(['password' => '[REDACTED]'], "channel [{$name}] logs passwords");
    }
});
