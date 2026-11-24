<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/** What one invitation needs to go out through WhatsApp. `$buttonSuffix` is the survey link token. */
final readonly class WhatsAppInvitation
{
    /** @param  list<string>  $bodyParameters */
    public function __construct(
        public string $mobile,
        public string $template,
        public array $bodyParameters,
        public string $buttonSuffix,
    ) {}
}
