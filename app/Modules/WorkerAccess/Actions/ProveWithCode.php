<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Modules\WorkerAccess\Support\ProofCodes;
use App\Modules\WorkerAccess\Support\ProofSteps;
use Illuminate\Support\Facades\Log;

/**
 * A person without a key proves it is them with the code they got by email, in the session they asked for it in. A person
 * with a key in use, or without an email, is refused whatever they write. The caller marks the proof in the session; logs never carry the code.
 */
final readonly class ProveWithCode
{
    public function __construct(private ProofCodes $codes, private ProofSteps $steps) {}

    /** @return 'passed'|'wrong'|'expired'|'locked'|'refused' */
    public function handle(string $employeeId, string $sessionId, string $code): string
    {
        if ($this->steps->mode($employeeId) !== 'code') {
            return 'refused';
        }

        $outcome = $this->codes->check($employeeId, $sessionId, $code);

        if ($outcome === 'passed') {
            Log::info('worker_access.proof_given', ['employee_id' => $employeeId, 'via' => 'code']);
        } else {
            Log::warning('worker_access.proof_refused', ['employee_id' => $employeeId, 'via' => 'code', 'reason' => $outcome]);
        }

        return $outcome;
    }
}
