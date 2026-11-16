<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\Organization\Contracts\Roster;

/**
 * How the person of a session can prove it is them (spec «Prueba reciente»): `key` when they have a key in use (never the
 * code, so the code is no way around the key), `code` by email when they have none, and `none` when they have neither a key
 * nor an email (they ask RH for their access). With the addresses the page or the form's step calls.
 */
final readonly class ProofSteps
{
    public function __construct(private Roster $roster) {}

    /**
     * @return array{mode: 'key'|'code'|'none', optionsUrl: string, proveUrl: string, sendCodeUrl: string, checkCodeUrl: string, minutes: int}
     */
    public function for(OpenSession $session, string $slug): array
    {
        return [
            'mode' => $this->mode($session->identity->employeeId),
            'optionsUrl' => route('worker.proof.options', $slug),
            'proveUrl' => route('worker.proof.store', $slug),
            'sendCodeUrl' => route('worker.proof.code.send', $slug),
            'checkCodeUrl' => route('worker.proof.code.check', $slug),
            'minutes' => config()->integer('worker_access.proof.code_minutes'),
        ];
    }

    /** @return 'key'|'code'|'none' */
    public function mode(string $employeeId): string
    {
        if (KeyAllowance::hasKey($employeeId)) {
            return 'key';
        }

        return $this->roster->find($employeeId)?->email === null ? 'none' : 'code';
    }
}
