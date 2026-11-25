<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Support;

use App\Modules\Attendance\Data\OfflineSubmission;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\WorkerAccess\Data\DeviceSigned;
use App\Modules\WorkerAccess\Data\SignedBy;
use Carbon\CarbonImmutable;

/**
 * A mark delivered without a connection once its ticket and its signature verified, with what the rest of the rules need:
 * the person, who signed, the ticket's issue and random value, the receipt and the hash of the challenge that was signed.
 * It lives only while one delivery is judged.
 */
final readonly class VerifiedOfflineMark
{
    public function __construct(
        public OfflineSubmission $submission,
        public RosterEntry $person,
        public SignedBy|DeviceSigned $signed,
        public CarbonImmutable $ticketIssuedAt,
        public string $nonce,
        public CarbonImmutable $receivedAt,
        public string $challengeHash,
    ) {}

    /** The key that signed: the person's passkey or the key of the device. */
    public function signerId(): string
    {
        return $this->signed instanceof SignedBy ? $this->signed->credentialId : $this->signed->deviceKeyId;
    }

    /** The passkey's credential, null when the key of the device signed. */
    public function credentialId(): ?string
    {
        return $this->signed instanceof SignedBy ? $this->signed->credentialId : null;
    }

    /** What the ticket proves of the time: from its issue to the receipt, in whole minutes. */
    public function intervalMinutes(): int
    {
        return intdiv($this->receivedAt->getTimestamp() - $this->ticketIssuedAt->getTimestamp(), 60);
    }
}
