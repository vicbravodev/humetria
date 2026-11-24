<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Data\SignedBy;
use App\Modules\WorkerAccess\Exceptions\InvalidCredential;
use Carbon\CarbonImmutable;

final readonly class CeremonySignatures implements WorkerSignatures
{
    public function __construct(private WebAuthnCeremony $ceremony) {}

    public function options(string $context): array
    {
        return $this->ceremony->assertionOptions($context);
    }

    public function verify(string $context, array $response): ?SignedBy
    {
        try {
            return $this->ceremony->verifyAssertion($context, $response);
        } catch (InvalidCredential) {
            return null;
        }
    }

    public function verifyChallenge(string $expected, array $response, CarbonImmutable $ticketIssuedAt): ?SignedBy
    {
        try {
            return $this->ceremony->verifyChallenge($expected, $response, $ticketIssuedAt);
        } catch (InvalidCredential) {
            return null;
        }
    }
}
