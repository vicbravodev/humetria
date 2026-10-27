<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Replaces the value of sensitive keys, at any depth, in a log record's
 * context and extra. Survey answers must never reach a log: that would
 * break the anonymity of Guías II/III.
 */
final class RedactSensitiveData implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        $sensitiveKeys = [
            'answers',
            'authorization',
            'cookie',
            'current_password',
            'password',
            'password_confirmation',
            'recovery_codes',
            'remember_token',
            'secret',
            'token',
            'two_factor_recovery_codes',
            'two_factor_secret',
            // A phone number reaches a person directly: never log one, whatever event carries it.
            'whatsapp',
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
