<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/**
 * One template message for the gateway. `$to` is the E.164 number as stored; each gateway adapts it to its provider. The link token
 * travels only in `$buttonSuffix`, never anywhere else.
 */
final readonly class OutboundMessage
{
    /** @param  list<string>  $bodyParameters */
    public function __construct(
        public string $to,
        public string $template,
        public string $language,
        public array $bodyParameters,
        public string $buttonSuffix,
        public string $callbackData,
    ) {}
}
