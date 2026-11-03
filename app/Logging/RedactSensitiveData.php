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
            // An email address identifies a person: never log one, whatever event carries it.
            'email',
            'password',
            'password_confirmation',
            'recovery_codes',
            'remember_token',
            // A sole proprietor's RFC is a person's tax ID: personal data, whatever event carries it.
            'rfc',
            'secret',
            'token',
            'two_factor_recovery_codes',
            'two_factor_secret',
            // A phone number reaches a person directly: never log one, whatever event carries it.
            'whatsapp',
            // NOM-037: a person's home, marital status, free-text notes and the address they answered from.
            'action_plan',
            'confirmed_ip',
            'contact_phone',
            'description',
            'exterior_number',
            'home_address',
            'interior_number',
            'ip',
            'marital_status',
            'note',
            'notes',
            'refusal_reason',
            'responded_ip',
            'review_note',
            'risk_note',
            'special_needs',
            'street',
            // NOM-037: that a person reported a severe traumatic event is health data.
            'traumatic_event',
            // Ley Silla: a person's footwear, discomfort, pain and the notes about their health are health data.
            'assessment_scores',
            'discomfort',
            'footwear',
            'health_note',
            'pain_level',
            'referral_note',
            // NOM-035: where a person was referred for a clinical assessment says something about their health.
            'institution',
            // NOM-035 complaints channel: what a person tells, who it is against, who they are, and the credentials of their case.
            'access_key',
            'body',
            'involved_text',
            'reference',
            'reporter_contact',
            'reporter_email',
            'reporter_name',
            'respondent_kind',
            'respondent_text',
            'ticket',
            // Humi: what a person asks the assistant, and what it answers, is never a log's business.
            'assistant_message',
            'prompt',
            'user_message',
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
