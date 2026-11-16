<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Jobs\PrepareDemo;
use App\Modules\Demos\Jobs\ResetDemo;
use App\Modules\Demos\Models\DemoRun;

/** Prepares or resets the demo on the queue; false, queuing nothing, while another run is going. */
final readonly class StartDemoRun
{
    public function handle(string $kind): bool
    {
        $run = DemoRun::start($kind);

        if ($run === null) {
            return false;
        }

        $kind === 'reset' ? ResetDemo::dispatch($run->id) : PrepareDemo::dispatch($run->id);

        return true;
    }
}
