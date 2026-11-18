<?php

declare(strict_types=1);

namespace App\Modules\Blog\Exceptions;

use RuntimeException;

final class InvalidArticle extends RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
