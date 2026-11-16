<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Jobs\SendRequestedLink;
use Illuminate\Support\Facades\Log;

/**
 * A person asks for a link by email (CUJ 3). Only when exactly one active person of the company has that email is a link
 * sent; with none or several nothing is, and the caller answers the same either way, so nobody learns who works there.
 * The email never reaches the log: only whether someone was found.
 */
final readonly class RequestAccessLink
{
    public function __construct(private Roster $roster, private Companies $companies) {}

    public function handle(string $email): void
    {
        $ids = $this->roster->idsByEmail($email);

        if (count($ids) !== 1) {
            Log::info('worker_access.link_requested', ['matched' => false]);

            return;
        }

        Log::info('worker_access.link_requested', ['matched' => true, 'employee_id' => $ids[0]]);
        SendRequestedLink::dispatch($this->companies->current()->id, $ids[0]);
    }
}
