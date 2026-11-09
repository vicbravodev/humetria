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
    // NOM-037: that a person reported a severe traumatic event is health data.
    'traumatic_event',
    // Ley Silla: a person's footwear, discomfort, pain and the notes about their health are health data.
    'health_note', 'pain_level', 'referral_note', 'assessment_scores', 'discomfort', 'footwear',
    // Humi: what a person asks the assistant, and what it answers, is never a log's business.
    'prompt', 'user_message', 'assistant_message',
    // Compensación: what a person earns is never a log's business.
    'salary', 'daily_salary', 'daily_salary_cents', 'salary_cents', 'sbc', 'amount_cents',
    // NOM-035: where a person was referred for a clinical assessment says something about their health.
    'institution',
    // NOM-035 Programa de intervención: what HR writes may name a person, so it never reaches a log.
    'analysis', 'follow_up_result', 'cancelled_reason',
    // NOM-035 complaints channel: what a person tells, who it is against, who they are, and the credentials of their case.
    'access_key', 'body', 'involved_text', 'reference', 'reporter_contact', 'reporter_email', 'reporter_name', 'respondent_kind', 'respondent_text', 'ticket',
    // NOM-035 policy and disclosure: the link of the page and of a person, the words of the policy and the note of a paper acknowledgment.
    'page_url', 'worker_page_link', 'personal_link', 'acknowledgment_note', 'sections', 'policy_text',
    // Registro de jornada: the key of a person's linked device, and where they stood when they marked.
    'credential_id', 'public_key', 'latitude', 'longitude',
    // ...and what the Marcar page sends: the signed assertion, the position, the screen's code; and a stored token's hash.
    'credential', 'lat', 'lng', 'q', 'token_hash',
]);

it('redacts every salary key at any depth', function (): void {
    Log::channel('single')->info('compensation.scenario_saved', [
        'scenario' => ['name' => 'Revisión 2027', 'rows' => [['category' => 'Oficial', 'Daily_Salary' => 51234567, 'sbc' => 52465]]],
        'salary' => 99887766,
        'totals' => ['salary_cents' => 11223344, 'daily_salary_cents' => 55667788, 'amount_cents' => 33445566],
    ]);

    $line = lastLogLine($this->logPath);

    expect($line['context']['scenario']['name'])->toBe('Revisión 2027')
        ->and($line['context']['scenario']['rows'][0]['category'])->toBe('Oficial')
        ->and($line['context']['scenario']['rows'][0]['Daily_Salary'])->toBe('[REDACTED]')
        ->and($line['context']['scenario']['rows'][0]['sbc'])->toBe('[REDACTED]')
        ->and($line['context']['salary'])->toBe('[REDACTED]')
        ->and($line['context']['totals'])->toBe(['salary_cents' => '[REDACTED]', 'daily_salary_cents' => '[REDACTED]', 'amount_cents' => '[REDACTED]'])
        ->and(File::get($this->logPath))->not->toContain('51234567')
        ->not->toContain('99887766')
        ->not->toContain('52465')
        ->not->toContain('11223344');
});

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
