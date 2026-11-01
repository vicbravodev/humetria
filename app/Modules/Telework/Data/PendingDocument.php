<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/** A published document the person has to read and confirm. */
final readonly class PendingDocument
{
    public function __construct(public string $id, public string $title, public string $publishedOn) {}
}
