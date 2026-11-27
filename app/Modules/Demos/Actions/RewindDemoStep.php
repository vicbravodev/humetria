<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Models\DemoSession;
use App\Modules\Demos\Support\TourCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The presenter goes back to a step of the session in course: its skip and the page visit that did it are forgotten. A step
 * done by the data stays done: what happened in the demo is not undone.
 */
final readonly class RewindDemoStep
{
    public function __construct(private TourCatalog $catalog) {}

    /** False, changing nothing, when no session is under way or it has no step `$key`. */
    public function handle(string $key): bool
    {
        $session = DemoSession::active();
        $step = $session === null ? null : $this->catalog->step($session, $key);

        if ($session === null || $step === null) {
            return false;
        }

        // jsonb's «-» takes a string out of an array, wherever it is; a step without a visit takes nothing out of visited.
        DB::update('UPDATE demo_sessions SET skipped_steps = skipped_steps - ?, visited = visited - ? WHERE id = ?', [$key, $step->visit ?? '', $session->id]);
        Log::info('demo.session_step_rewound', ['session_id' => $session->id, 'step' => $key]);

        return true;
    }
}
