<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Data;

/**
 * What the provider answered to one send: accepted with its message id, or failed with a cause.
 */
final readonly class SendOutcome
{
    private function __construct(
        public ?string $providerMessageId,
        public ?FailureCause $cause,
    ) {}

    public static function accepted(string $providerMessageId): self
    {
        return new self($providerMessageId, null);
    }

    public static function failed(FailureCause $cause): self
    {
        return new self(null, $cause);
    }

    public function isAccepted(): bool
    {
        return $this->cause === null;
    }
}
