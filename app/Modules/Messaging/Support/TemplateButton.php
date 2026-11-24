<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

use App\Modules\Messaging\Enums\TemplateButtonType;
use InvalidArgumentException;

/**
 * One button of a template, with Meta's rules checked on construction. A URL button carries at most one variable, `{{1}}`, and
 * only at the end (Meta's rule); `$pending` marks a button whose destination does not exist yet and says what is missing.
 */
final readonly class TemplateButton
{
    /** The label of the quick reply the webhook reads as a STOP. */
    public const string OPT_OUT_LABEL = 'Dejar de recibir';

    private function __construct(
        public TemplateButtonType $type,
        public string $text,
        public ?string $url = null,
        public ?string $phone = null,
        public bool $variable = false,
        public ?string $example = null,
        public ?string $pending = null,
    ) {
        if ($text === '' || mb_strlen($text) > 25) {
            throw new InvalidArgumentException("The text of a button has 1 to 25 characters [{$text}].");
        }

        if ($pending !== null && trim($pending) === '') {
            throw new InvalidArgumentException('A pending button says what is missing.');
        }

        if ($url !== null) {
            $this->checkUrl($url, $variable);
        }
    }

    /** A URL whose last segment is the variable (the link token); `$example` is the value Meta is shown. */
    public static function urlWithVariable(string $text, string $url, string $example): self
    {
        if (! str_ends_with($url, '{{1}}')) {
            throw new InvalidArgumentException("A URL variable goes at the end [{$url}].");
        }

        return new self(TemplateButtonType::Url, $text, url: $url, variable: true, example: $example);
    }

    public static function fixedUrl(string $text, string $url): self
    {
        return new self(TemplateButtonType::Url, $text, url: $url);
    }

    /** A URL button whose route does not exist yet: it ends in a variable, which nothing provides today. */
    public static function pendingUrl(string $text, string $reason): self
    {
        return new self(TemplateButtonType::Url, $text, variable: true, example: 'token', pending: $reason);
    }

    public static function quickReply(string $text): self
    {
        return new self(TemplateButtonType::QuickReply, $text);
    }

    public static function phone(string $text, string $number): self
    {
        return new self(TemplateButtonType::Phone, $text, phone: $number);
    }

    public static function pendingPhone(string $text, string $reason): self
    {
        return new self(TemplateButtonType::Phone, $text, pending: $reason);
    }

    public static function copyCode(string $text): self
    {
        return new self(TemplateButtonType::CopyCode, $text);
    }

    private function checkUrl(string $url, bool $variable): void
    {
        $count = substr_count($url, '{{');

        if ($variable ? ($count !== 1 || ! str_ends_with($url, '{{1}}')) : $count !== 0) {
            throw new InvalidArgumentException("A URL has no variable, or one `{{1}}` at the end [{$url}].");
        }
    }
}
