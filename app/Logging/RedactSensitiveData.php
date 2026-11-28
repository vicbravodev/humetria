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
    /**
     * The keys whose value never reaches a log, at any depth. The audit trail's detail hides them too, unless it names one
     * on purpose (AuditPages::KEYS).
     *
     * @var list<string>
     */
    public const array KEYS = [
        'answers', // @pest-mutate-ignore: RemoveArrayItem
        'authorization', // @pest-mutate-ignore: RemoveArrayItem
        // Clima y pulso: what a person writes, the themes drawn from it, and what a company commits to answer it.
        'comment', // @pest-mutate-ignore: RemoveArrayItem
        'comments', // @pest-mutate-ignore: RemoveArrayItem
        'cookie', // @pest-mutate-ignore: RemoveArrayItem
        'current_password', // @pest-mutate-ignore: RemoveArrayItem
        // An email address identifies a person: never log one, whatever event carries it.
        'email', // @pest-mutate-ignore: RemoveArrayItem
        'password', // @pest-mutate-ignore: RemoveArrayItem
        'password_confirmation', // @pest-mutate-ignore: RemoveArrayItem
        'recovery_codes', // @pest-mutate-ignore: RemoveArrayItem
        'remember_token', // @pest-mutate-ignore: RemoveArrayItem
        // A sole proprietor's RFC is a person's tax ID: personal data, whatever event carries it.
        'rfc', // @pest-mutate-ignore: RemoveArrayItem
        'secret', // @pest-mutate-ignore: RemoveArrayItem
        'token', // @pest-mutate-ignore: RemoveArrayItem
        'two_factor_recovery_codes', // @pest-mutate-ignore: RemoveArrayItem
        'two_factor_secret', // @pest-mutate-ignore: RemoveArrayItem
        // A phone number reaches a person directly: never log one, whatever event carries it.
        'whatsapp', // @pest-mutate-ignore: RemoveArrayItem
        // WhatsApp: a phone number reaches a person, and what Meta sends or asks for is a credential or a person's data.
        'app_secret', // @pest-mutate-ignore: RemoveArrayItem
        'components', // @pest-mutate-ignore: RemoveArrayItem
        'hub_verify_token', // @pest-mutate-ignore: RemoveArrayItem
        'mobile', // @pest-mutate-ignore: RemoveArrayItem
        'msisdn', // @pest-mutate-ignore: RemoveArrayItem
        'phone', // @pest-mutate-ignore: RemoveArrayItem
        'recipient_hash', // @pest-mutate-ignore: RemoveArrayItem
        'verify_token', // @pest-mutate-ignore: RemoveArrayItem
        'wa_id', // @pest-mutate-ignore: RemoveArrayItem
        // NOM-037: a person's home, marital status, free-text notes and the address they answered from.
        'action_plan', // @pest-mutate-ignore: RemoveArrayItem
        'confirmed_ip', // @pest-mutate-ignore: RemoveArrayItem
        'contact_phone', // @pest-mutate-ignore: RemoveArrayItem
        'description', // @pest-mutate-ignore: RemoveArrayItem
        'exterior_number', // @pest-mutate-ignore: RemoveArrayItem
        'heard', // @pest-mutate-ignore: RemoveArrayItem
        'home_address', // @pest-mutate-ignore: RemoveArrayItem
        'interior_number', // @pest-mutate-ignore: RemoveArrayItem
        'ip', // @pest-mutate-ignore: RemoveArrayItem
        'marital_status', // @pest-mutate-ignore: RemoveArrayItem
        'note', // @pest-mutate-ignore: RemoveArrayItem
        'notes', // @pest-mutate-ignore: RemoveArrayItem
        'owner_name', // @pest-mutate-ignore: RemoveArrayItem
        'refusal_reason', // @pest-mutate-ignore: RemoveArrayItem
        'responded_ip', // @pest-mutate-ignore: RemoveArrayItem
        'review_note', // @pest-mutate-ignore: RemoveArrayItem
        'risk_note', // @pest-mutate-ignore: RemoveArrayItem
        'special_needs', // @pest-mutate-ignore: RemoveArrayItem
        'street', // @pest-mutate-ignore: RemoveArrayItem
        'themes', // @pest-mutate-ignore: RemoveArrayItem
        'will_do', // @pest-mutate-ignore: RemoveArrayItem
        // NOM-037: that a person reported a severe traumatic event is health data.
        'traumatic_event', // @pest-mutate-ignore: RemoveArrayItem
        // Ley Silla: a person's footwear, discomfort, pain and the notes about their health are health data.
        'assessment_scores', // @pest-mutate-ignore: RemoveArrayItem
        'discomfort', // @pest-mutate-ignore: RemoveArrayItem
        'footwear', // @pest-mutate-ignore: RemoveArrayItem
        'health_note', // @pest-mutate-ignore: RemoveArrayItem
        'pain_level', // @pest-mutate-ignore: RemoveArrayItem
        'referral_note', // @pest-mutate-ignore: RemoveArrayItem
        // Compensación: what a person earns, and the salary base built from it, is never a log's business.
        'amount_cents', // @pest-mutate-ignore: RemoveArrayItem
        'daily_salary', // @pest-mutate-ignore: RemoveArrayItem
        'daily_salary_cents', // @pest-mutate-ignore: RemoveArrayItem
        'salary', // @pest-mutate-ignore: RemoveArrayItem
        'salary_cents', // @pest-mutate-ignore: RemoveArrayItem
        'sbc', // @pest-mutate-ignore: RemoveArrayItem
        // NOM-035: where a person was referred for a clinical assessment says something about their health.
        'institution', // @pest-mutate-ignore: RemoveArrayItem
        // NOM-035 Programa de intervención: what HR writes may name a person, so it never reaches a log.
        'analysis', // @pest-mutate-ignore: RemoveArrayItem
        'cancelled_reason', // @pest-mutate-ignore: RemoveArrayItem
        'follow_up_result', // @pest-mutate-ignore: RemoveArrayItem
        // NOM-035 complaints channel: what a person tells, who it is against, who they are, and the credentials of their case.
        'access_key', // @pest-mutate-ignore: RemoveArrayItem
        'body', // @pest-mutate-ignore: RemoveArrayItem
        'involved_text', // @pest-mutate-ignore: RemoveArrayItem
        'reference', // @pest-mutate-ignore: RemoveArrayItem
        'reporter_contact', // @pest-mutate-ignore: RemoveArrayItem
        'reporter_email', // @pest-mutate-ignore: RemoveArrayItem
        'reporter_name', // @pest-mutate-ignore: RemoveArrayItem
        'respondent_kind', // @pest-mutate-ignore: RemoveArrayItem
        'respondent_text', // @pest-mutate-ignore: RemoveArrayItem
        'ticket', // @pest-mutate-ignore: RemoveArrayItem
        // Registro de jornada, marks made without a connection: the list of tickets a phone keeps.
        'tickets', // @pest-mutate-ignore: RemoveArrayItem
        // NOM-035 policy and disclosure: the link of the page of the workers (a credential), a person's own link, the words of a
        // policy and the note of a paper acknowledgment never reach a log.
        'acknowledgment_note', // @pest-mutate-ignore: RemoveArrayItem
        'page_url', // @pest-mutate-ignore: RemoveArrayItem
        'personal_link', // @pest-mutate-ignore: RemoveArrayItem
        'policy_text', // @pest-mutate-ignore: RemoveArrayItem
        'sections', // @pest-mutate-ignore: RemoveArrayItem
        'worker_page_link', // @pest-mutate-ignore: RemoveArrayItem
        // Registro de jornada: the key of a person's linked device identifies it, and where a person stood when they
        // marked is personal data (criterio 3.4).
        'credential_id', // @pest-mutate-ignore: RemoveArrayItem
        'latitude', // @pest-mutate-ignore: RemoveArrayItem
        'longitude', // @pest-mutate-ignore: RemoveArrayItem
        'public_key', // @pest-mutate-ignore: RemoveArrayItem
        // ...and what the Marcar page sends: the signed assertion of the key, the position and the screen's code (a
        // credential while it lives; `q` is also a search, which may name a person). A stored token's hash too.
        'credential', // @pest-mutate-ignore: RemoveArrayItem
        'lat', // @pest-mutate-ignore: RemoveArrayItem
        'lng', // @pest-mutate-ignore: RemoveArrayItem
        'q', // @pest-mutate-ignore: RemoveArrayItem
        'token_hash', // @pest-mutate-ignore: RemoveArrayItem
        // Humi: what a person asks the assistant, and what it answers, is never a log's business.
        'assistant_message', // @pest-mutate-ignore: RemoveArrayItem
        'prompt', // @pest-mutate-ignore: RemoveArrayItem
        'user_message', // @pest-mutate-ignore: RemoveArrayItem
        // Vacaciones: why a request was turned down is written by a person, about a person.
        'rejection_reason', // @pest-mutate-ignore: RemoveArrayItem
        // Inicio de sesión único: the secret of a company's identity provider and what it hands back are credentials.
        'client_secret', // @pest-mutate-ignore: RemoveArrayItem
        'id_token', // @pest-mutate-ignore: RemoveArrayItem
        'access_token', // @pest-mutate-ignore: RemoveArrayItem
        'code_verifier', // @pest-mutate-ignore: RemoveArrayItem
        // Llave del dispositivo (plan B de las marcas sin conexión): the possession challenge, the signature, the pass and the
        // key's id are credentials of a person's phone.
        'challenge', // @pest-mutate-ignore: RemoveArrayItem
        'device_key_id', // @pest-mutate-ignore: RemoveArrayItem
        'pass', // @pest-mutate-ignore: RemoveArrayItem
        'signature', // @pest-mutate-ignore: RemoveArrayItem
        // Registro de jornada, marks made without a connection: the screen's code, the note of the person (it may speak of their
        // health) and the position in the integers the phone signs.
        'qr', // @pest-mutate-ignore: RemoveArrayItem
        'justification', // @pest-mutate-ignore: RemoveArrayItem
        'lat_e5', // @pest-mutate-ignore: RemoveArrayItem
        'lng_e5', // @pest-mutate-ignore: RemoveArrayItem
        // Avisos push: where a person's phone receives them (the address of its push service and its keys) identifies the phone.
        'endpoint', // @pest-mutate-ignore: RemoveArrayItem
        'p256dh', // @pest-mutate-ignore: RemoveArrayItem
        'auth', // @pest-mutate-ignore: RemoveArrayItem
    ];

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
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::KEYS, true)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
