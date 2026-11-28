<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/**
 * One WhatsApp template as code defines it and what it sent. `category` is Meta's name (UTILITY, AUTHENTICATION); `pendingInCode`
 * is set while a button of it waits for a route: it is not Meta's approval, which the app does not know.
 */
final readonly class TemplateTraffic
{
    public function __construct(
        public string $key,
        public string $category,
        public bool $pendingInCode,
        public int $total,
        public int $delivered,
        public int $rejected,
    ) {}
}
