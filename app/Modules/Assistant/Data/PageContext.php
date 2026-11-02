<?php

declare(strict_types=1);

namespace App\Modules\Assistant\Data;

/**
 * The page the person has open, once the server has checked it belongs to their company: `kind` is campaign or
 * work_center, and `label` is the name the server gives it, never the one the browser sent.
 */
final readonly class PageContext
{
    public function __construct(
        public string $kind,
        public string $id,
        public string $label,
    ) {}
}
