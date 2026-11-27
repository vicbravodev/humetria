<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Models\DemoSession;
use App\Modules\Demos\Support\TourCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** The presenter moves past a step of the session in course, done or not («Saltar paso»); each step is kept once. */
final readonly class SkipDemoStep
{
    public function __construct(private TourCatalog $catalog) {}

    /** False, changing nothing, when no session is under way or it has no step `$key`. */
    public function handle(string $key): bool
    {
        $session = DemoSession::active();

        if ($session === null || $this->catalog->step($session, $key) === null) {
            return false;
        }

        $skipped = DB::update(
            'UPDATE demo_sessions SET skipped_steps = skipped_steps || jsonb_build_array(?::text) WHERE id = ? AND NOT skipped_steps @> jsonb_build_array(?::text)',
            [$key, $session->id, $key],
        );

        if ($skipped > 0) {
            Log::info('demo.session_step_skipped', ['session_id' => $session->id, 'step' => $key]);
        }

        return true;
    }
}
