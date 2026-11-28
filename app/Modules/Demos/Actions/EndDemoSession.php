<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Actions\RecordAudit;
use App\Modules\Demos\Enums\DemoSessionStatus;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Support\Facades\Log;

/**
 * Ends the open session, or the last one when its preparation failed: it goes to «ending» and the demo resets, wiping
 * whatever the audience left (or the half-made demo); ResetDemo marks it ended when the reset finishes. A session already ending may be ended again (its reset failed). When the reset cannot
 * start («busy», «unqueued») the session stays as it was; «none» when no session is open.
 */
final readonly class EndDemoSession
{
    public function __construct(private StartDemoRun $runs, private RecordAudit $audit, private DemoCompanies $demos) {}

    /** @return 'started'|'busy'|'unqueued'|'none' */
    public function handle(): string
    {
        $session = DemoSession::toEnd();

        if ($session === null) {
            return 'none';
        }

        $was = $session->status;

        // Before the reset is queued: it may finish, and look for the session ending, before this returns. A session
        // another request just moved to ending is that request's to end.
        $moved = $was !== DemoSessionStatus::Ending && $session->markEnding();

        if ($was !== DemoSessionStatus::Ending && ! $moved) {
            return 'busy';
        }

        $outcome = $this->runs->handle('reset');

        if ($outcome !== 'started') {
            if ($moved) {
                $session->undoEnding($was);
            }

            return $outcome;
        }

        // Once per end: a second try of a session already ending was audited by the call that moved it.
        if ($moved) {
            $this->audit->handle('demo.session_ended', $session, ['tenant_id' => $this->demos->id()]);
        }

        Log::info('demo.session_ended', ['session_id' => $session->id, 'retried' => ! $moved]);

        return $outcome;
    }
}
