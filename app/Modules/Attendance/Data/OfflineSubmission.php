<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

use App\Modules\Attendance\Enums\MarkType;
use App\Modules\Attendance\Enums\SignerType;
use Carbon\CarbonImmutable;

/**
 * One element of a delivery of marks made without a connection (criterio 3.3.1), as the phone sent it: the ticket that bounds
 * its time, the time the person declared, the position as the integers that were signed (a hundred-thousandth of a degree) and
 * the signature, from the person's key or from the key of the device with its pass. `seq` is the phone's own number: it
 * names the mark in the answer and goes into the challenge, and orders nothing but the processing of one delivery.
 */
final readonly class OfflineSubmission
{
    /**
     * @param  array<array-key, mixed>|null  $credential  the passkey's assertion as JSON, when the person's key signed
     */
    public function __construct(
        public int $seq,
        public MarkType $type,
        public string $ticket,
        public string $workCenterId,
        public ?string $qr,
        public CarbonImmutable $declaredAt,
        public ?int $latE5,
        public ?int $lngE5,
        public ?int $accuracyM,
        public ?CarbonImmutable $deviceClockAt,
        public ?string $justification,
        public int $agreementVersion,
        public SignerType $signerType,
        public ?array $credential = null,
        public ?string $deviceKeyId = null,
        public ?string $signature = null,
        public ?string $pass = null,
    ) {}

    /** The position the phone read, in degrees; null when it read none. */
    public function position(): ?Position
    {
        return $this->latE5 === null || $this->lngE5 === null || $this->accuracyM === null
            ? null
            : new Position($this->latE5 / 1e5, $this->lngE5 / 1e5, (float) $this->accuracyM);
    }

    /** What the signature is made of, to tell a repeated delivery from a different one with the same ticket. */
    public function assertion(): string
    {
        $signature = $this->signerType === SignerType::Passkey ? data_get($this->credential, 'response.signature') : $this->signature;

        return is_string($signature) ? $signature : '';
    }
}
