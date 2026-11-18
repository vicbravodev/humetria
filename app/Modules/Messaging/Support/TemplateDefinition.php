<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

/**
 * One WhatsApp template as it is registered with Meta: name, language, category, body and the URL button.
 */
final readonly class TemplateDefinition
{
    public function __construct(
        public string $key,
        public string $language,
        public string $category,
        public string $body,
        public int $bodyParameterCount,
        public string $buttonLabel,
        public string $buttonUrlBase,
    ) {}
}
