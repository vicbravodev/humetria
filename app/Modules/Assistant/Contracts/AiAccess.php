<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Contracts;

/**
 * What another module needs to call the AI provider for the current company without skipping the company's switch or
 * budget for Humi: ask `available()` first, send only `redact()`ed text, and `record()` the tokens used.
 */
interface AiAccess
{
    /** Humi is not turned off for the current company and its budget of tokens of the month is not used up. */
    public function available(): bool;

    /** Masks emails, CURP, RFC, NSS and phones before the text leaves for the provider. */
    public function redact(string $text): string;

    /** Counts one call to the provider against the month of the current company. No person, no conversation, no text. */
    public function record(string $agent, int $inputTokens, int $outputTokens, int $latencyMs): void;
}
