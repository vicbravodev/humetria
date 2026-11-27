<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Actions\RecordAudit;
use App\Modules\Demos\Models\DemoSession;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Support\Facades\Log;

/**
 * Starts a demo session: opens it, preparing, and prepares the demo for it on the queue. «busy», leaving nothing behind,
 * while another session is open or a preparation runs; «unqueued» fails the session when the queue cannot take the job.
 */
final readonly class StartDemoSession
{
    public function __construct(private StartDemoRun $runs, private RecordAudit $audit, private DemoCompanies $demos) {}

    /**
     * @param  list<string>  $tours
     * @return 'started'|'busy'|'unqueued'
     */
    public function handle(?string $clientName, array $tours): string
    {
        $session = DemoSession::start($clientName, $tours);

        if ($session === null) {
            return 'busy';
        }

        $outcome = $this->runs->handle('session', $session->id);

        if ($outcome === 'busy') {
            $session->delete();
        } elseif ($outcome === 'unqueued') {
            $session->markFailed();
        } else {
            // Ids only: the client's name stays out of the trail and the log.
            $this->audit->handle('demo.session_started', $session, ['tenant_id' => $this->demos->id()]);
            Log::info('demo.session_started', ['session_id' => $session->id, 'tours' => $tours]);
        }

        return $outcome;
    }
}
