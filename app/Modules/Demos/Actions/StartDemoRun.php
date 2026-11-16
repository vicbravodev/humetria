<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Actions\RecordAudit;
use App\Modules\Demos\Jobs\PrepareDemo;
use App\Modules\Demos\Jobs\ResetDemo;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Support\Facades\Bus;
use Throwable;

/**
 * Prepares or resets the demo on the queue: «started», or «busy», queuing nothing, while another run is going. When the
 * queue cannot take the job (Redis down), the run fails at once, so its lock does not stay held for half an hour:
 * «unqueued».
 */
final readonly class StartDemoRun
{
    public function __construct(private RecordAudit $audit, private DemoCompanies $demos) {}

    /** @return 'started'|'busy'|'unqueued' */
    public function handle(string $kind): string
    {
        $run = DemoRun::start($kind);

        if ($run === null) {
            return 'busy';
        }

        // The demo company's trail once it exists (the very first preparation is the platform's); ids only.
        $this->audit->handle("demo.{$kind}_started", $run, ['tenant_id' => $this->demos->id()]);

        try {
            Bus::dispatch($kind === 'reset' ? new ResetDemo($run->id) : new PrepareDemo($run->id));
        } catch (Throwable $error) {
            $run->fail($error);

            return 'unqueued';
        }

        return 'started';
    }
}
