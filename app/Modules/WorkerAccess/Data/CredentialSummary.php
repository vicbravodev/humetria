<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Data;

use Carbon\CarbonImmutable;

/**
 * What another module may know of a person's key: its id, its label and when it was registered, last used and revoked. Never its
 * credential id or public key. `synced` is a key that may live in the person's passkey account, not provably on one phone.
 */
final readonly class CredentialSummary
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $synced,
        public CarbonImmutable $registeredAt,
        public ?CarbonImmutable $lastUsedAt,
        public ?CarbonImmutable $revokedAt,
    ) {}
}
