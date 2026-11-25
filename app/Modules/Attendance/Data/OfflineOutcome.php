<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Data;

use App\Modules\Attendance\Enums\OfflineRejection;

/**
 * What became of one element of a delivery (the network contract): `received`, `duplicate` (the same delivery again, with what
 * was kept), `rejected` or `conflict` (a ticket used for something else). It says nothing of the mark: no time, no position.
 * `signedEmployeeId` is for whoever answers, never for the phone: the person whose signature verified.
 */
final readonly class OfflineOutcome
{
    /** The reasons that are only answered, with no row kept. */
    public const string TICKET_INVALID = 'ticket_invalid';

    public const string SIGNATURE_INVALID = 'signature_invalid';

    public const string TICKET_REUSED = 'ticket_reused';

    /** A copy of a mark that was already made with a connection: the phone drops it. */
    public const string ALREADY_RECORDED = 'already_recorded';

    public function __construct(
        public int $seq,
        public string $status,
        public ?string $reason = null,
        public ?string $storedStatus = null,
        public ?string $signedEmployeeId = null,
    ) {}

    public static function received(int $seq, string $employeeId): self
    {
        return new self($seq, 'received', signedEmployeeId: $employeeId);
    }

    public static function rejected(int $seq, OfflineRejection|string $reason, ?string $employeeId = null): self
    {
        return new self($seq, 'rejected', $reason instanceof OfflineRejection ? $reason->value : $reason, signedEmployeeId: $employeeId);
    }

    public static function duplicate(int $seq, string $storedStatus, ?string $reason, string $employeeId): self
    {
        return new self($seq, 'duplicate', $reason, $storedStatus, $employeeId);
    }

    /** No signature was verified for it: nobody is named. */
    public static function conflict(int $seq): self
    {
        return new self($seq, 'conflict', self::TICKET_REUSED);
    }

    /**
     * @return array{seq: int, status: string, reason?: string, stored_status?: string}
     */
    public function toArray(): array
    {
        return [
            'seq' => $this->seq,
            'status' => $this->status,
            ...($this->reason === null ? [] : ['reason' => $this->reason]),
            ...($this->storedStatus === null ? [] : ['stored_status' => $this->storedStatus]),
        ];
    }
}
