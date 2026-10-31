<?php

declare(strict_types=1);

use App\Logging\RedactSensitiveData;
use App\Logging\StructuredLogging;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\NullHandler;
use Monolog\Logger as Monolog;
use Psr\Log\NullLogger;

mutates(RedactSensitiveData::class, StructuredLogging::class);

beforeEach(function (): void {
    $this->logPath = storage_path('logs/structured-test.log');
    File::delete($this->logPath);
    config(['logging.channels.single.path' => $this->logPath]);
    Log::forgetChannel('single');
});

afterEach(function (): void {
    File::delete($this->logPath);
});

/**
 * @return array<string, mixed>
 */
function lastLogLine(string $path): array
{
    $lines = array_filter(explode("\n", File::get($path)));

    /** @var array<string, mixed> $decoded */
    $decoded = json_decode((string) end($lines), true, flags: JSON_THROW_ON_ERROR);

    return $decoded;
}

it('writes one JSON object per line with the request context as metadata', function (): void {
    Context::add('request_id', 'req-123');

    Log::channel('single')->info('campaign.launched', ['campaign_id' => 'c-1']);

    $line = lastLogLine($this->logPath);

    expect($line['message'])->toBe('campaign.launched')
        ->and($line['context'])->toBe(['campaign_id' => 'c-1'])
        ->and($line['extra']['request_id'])->toBe('req-123');
});

it('redacts sensitive keys at any depth, case-insensitively', function (): void {
    Context::add('Authorization', 'Bearer secret-token');

    Log::channel('single')->info('survey.submitted', [
        'password' => 'hunter2',
        'payload' => ['answers' => [1 => 'siempre'], 'Token' => 'abc', 'area' => 'Ventas'],
    ]);

    $line = lastLogLine($this->logPath);

    expect($line['context']['password'])->toBe('[REDACTED]')
        ->and($line['context']['payload']['answers'])->toBe('[REDACTED]')
        ->and($line['context']['payload']['Token'])->toBe('[REDACTED]')
        ->and($line['context']['payload']['area'])->toBe('Ventas')
        ->and($line['extra']['Authorization'])->toBe('[REDACTED]')
        ->and(File::get($this->logPath))->not->toContain('hunter2')
        ->not->toContain('siempre')
        ->not->toContain('secret-token');
});

it('redacts each sensitive key', function (string $key): void {
    Log::channel('single')->info('test', [$key => 'leak']);

    expect(lastLogLine($this->logPath)['context'][$key])->toBe('[REDACTED]');
})->with([
    'answers', 'authorization', 'cookie', 'current_password', 'email', 'password',
    'password_confirmation', 'recovery_codes', 'remember_token', 'secret',
    'rfc', 'token', 'two_factor_recovery_codes', 'two_factor_secret', 'whatsapp',
    // NOM-037: a person's home, health-adjacent notes and the address they answered from.
    'risk_note', 'special_needs', 'description', 'marital_status', 'contact_phone', 'home_address', 'street',
    'exterior_number', 'interior_number', 'refusal_reason', 'review_note', 'notes', 'note', 'action_plan',
    'ip', 'confirmed_ip', 'responded_ip',
]);

it('keeps lists with numeric keys intact', function (): void {
    Log::channel('single')->info('employees.imported', ['rows' => [10, 20]]);

    expect(lastLogLine($this->logPath)['context']['rows'])->toBe([10, 20]);
});

it('leaves loggers that are not Monolog untouched', function (): void {
    $logger = new Logger(new NullLogger);

    (new StructuredLogging)($logger);

    expect($logger->getLogger())->toBeInstanceOf(NullLogger::class);
});

it('skips handlers without a formatter but still redacts their records', function (): void {
    $monolog = new Monolog('test', [new NullHandler]);

    (new StructuredLogging)(new Logger($monolog));

    expect($monolog->getProcessors())->toHaveCount(1)
        ->and($monolog->getProcessors()[0])->toBeInstanceOf(RedactSensitiveData::class);
});
