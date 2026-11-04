<?php

declare(strict_types=1);

namespace App\Modules\Complaints\Support;

/** What the person takes with them: the reference, and the key that is shown once and kept only as a hash. */
final readonly class Receipt
{
    public function __construct(public string $reference, public string $key) {}
}
