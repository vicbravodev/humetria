<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Notifications\ProofCode;
use App\Modules\WorkerAccess\Support\KeyAllowance;
use App\Modules\WorkerAccess\Support\ProofCodes;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Emails a person without a key the code to prove it is them. A person with a key in use never gets one (the code is no way
 * around the key), nor one without an email. A person, and a session, ask for at most 3 codes in 10 minutes. The email goes
 * right away, never through the queue: the code must not sit in it. Logs carry the person's id only.
 */
final readonly class SendProofCode
{
    public function __construct(private Roster $roster, private Companies $companies, private ProofCodes $codes) {}

    /** @return 'sent'|'busy'|'refused' */
    public function handle(string $employeeId, string $sessionId): string
    {
        $person = $this->roster->find($employeeId);

        if ($person?->email === null || KeyAllowance::hasKey($employeeId)) {
            return 'refused';
        }

        $counters = ['worker-proof-code-person:'.$employeeId, 'worker-proof-code-session:'.hash('sha256', $sessionId)];

        foreach ($counters as $counter) {
            if (RateLimiter::tooManyAttempts($counter, config()->integer('worker_access.proof.code_sends'))) {
                return 'busy';
            }
        }

        foreach ($counters as $counter) {
            RateLimiter::hit($counter, config()->integer('worker_access.proof.code_minutes') * 60);
        }

        $code = $this->codes->issue($employeeId, $sessionId);
        Notification::route('mail', $person->email)->notifyNow(new ProofCode($code, $person->name, $this->companies->current()));
        Log::info('worker_access.proof_code_sent', ['employee_id' => $employeeId]);

        return 'sent';
    }
}
