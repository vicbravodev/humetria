<?php

declare(strict_types=1);

namespace App\Modules\Announcements\Data;

/**
 * The banner a company's panel shows. `id` changes when the announcement does, so a banner closed before comes back once edited.
 */
final readonly class CompanyNotice
{
    public function __construct(public string $id, public string $kind, public string $message, public bool $dismissible) {}

    /**
     * @return array{id: string, kind: string, message: string, dismissible: bool}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'kind' => $this->kind, 'message' => $this->message, 'dismissible' => $this->dismissible];
    }
}
