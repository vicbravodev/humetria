<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Contracts;

use App\Modules\WorkerAccess\Data\DeviceSigned;
use Carbon\CarbonImmutable;

/**
 * The signature of a person's device key (plan B of the offline marks: the key the app keeps in the phone, with a pass that vouches
 * for it). Attendance rebuilds the challenge of a mark, asks here whose key signed it and judges the answer against the declared
 * time. Reading only: no cache, nothing written.
 */
interface DeviceKeySignatures
{
    /**
     * The id of `$pass` when its HMAC verifies and it is of the current company; null otherwise. It ignores the expiry and the key's
     * revocation: Attendance needs it to rebuild the challenge before verifying.
     */
    public function passId(string $pass): ?string;

    /**
     * Whose device key signed `$expected` (the 32 raw bytes of the challenge) holding `$pass`. A key revoked after `$ticketIssuedAt`
     * still verifies and carries its `revokedAt`; one revoked at that instant or before does not. Null for any answer that does not
     * verify (one answer for every cause). It does not judge the expiry of the pass nor whether the person is still active.
     *
     * @param  string  $expected  the 32 raw bytes of the SHA-256 of the canonical string of the mark
     * @param  string  $signature  r‖s of 64 bytes in base64url, as WebCrypto gives it
     */
    public function verify(string $expected, string $signature, string $pass, CarbonImmutable $ticketIssuedAt): ?DeviceSigned;
}
