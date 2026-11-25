<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use Webauthn\Counter\CounterChecker;
use Webauthn\CredentialRecord;

/**
 * A counter check that checks nothing, for `WebAuthnCeremony::verifyChallenge`: synced passkeys always report 0, and a mark
 * delivered out of order would break the counter of a key bound to its device. The online verification (`verifyAssertion`)
 * keeps the library's own check, which throws when the counter does not grow.
 */
final readonly class IgnoreCounter implements CounterChecker
{
    public function check(CredentialRecord $credentialRecord, int $currentCounter): void {}
}
