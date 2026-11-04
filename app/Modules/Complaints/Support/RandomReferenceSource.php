<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

final readonly class RandomReferenceSource implements ReferenceSource
{
    public function next(): string
    {
        return RandomCode::make(8);
    }
}
