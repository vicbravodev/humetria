<?php

declare(strict_types=1);

namespace App\Modules\Telework\Data;

/**
 * Photos the Comisión asked a person for, as their portal lists them. `note` says what to photograph.
 */
final readonly class PendingPhotoRequest
{
    public function __construct(public string $id, public string $workplace, public string $note) {}
}
