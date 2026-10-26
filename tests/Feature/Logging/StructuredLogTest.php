<?php

declare(strict_types=1);

use App\Logging\RedactSensitiveData;
use App\Logging\StructuredLogging;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

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
    'answers', 'authorization', 'cookie', 'current_password', 'password',
    'password_confirmation', 'recovery_codes', 'remember_token', 'secret',
    'token', 'two_factor_recovery_codes', 'two_factor_secret',
]);

it('keeps lists with numeric keys intact', function (): void {
    Log::channel('single')->info('employees.imported', ['rows' => [10, 20]]);

    expect(lastLogLine($this->logPath)['context']['rows'])->toBe([10, 20]);
});
