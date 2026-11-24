<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Controllers;

use App\Modules\Attendance\Http\Middleware\AttachOfflineTickets;
use App\Modules\Attendance\Http\Requests\OfflineTicketsRequest;
use App\Modules\Attendance\Support\OfflineTicket;
use App\Modules\Organization\Contracts\Roster;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * What a phone does to mark without a connection (criterio 3.3.1), with no session, no cookie and no forgery check: after a
 * blackout there may be none. The credential is the ticket here (and the signature of each mark, in the delivery).
 */
final readonly class OfflineController
{
    public function __construct(private Roster $roster) {}

    /**
     * A new batch of tickets for a valid ticket of the person, of any age within the horizon of a correction. A ticket is
     * inert without the signature of a mark, and a newer one only narrows the lower bound of the next, so this asks for no
     * more than the ticket. The same 404 for a ticket that does not verify, is too old, or is of someone who left.
     */
    public function tickets(OfflineTicketsRequest $request, OfflineTicket $tickets): JsonResponse
    {
        $verified = $tickets->verify($request->ticket());
        $person = $verified === null ? null : $this->roster->find($verified['employee_id']);

        if ($verified === null || $person === null || ! $person->active
            || $verified['issued_at']->addDays(config()->integer('attendance.offline.ticket_horizon_days'))->lessThan(CarbonImmutable::now())) {
            abort(404);
        }

        $queue = $request->queue();

        if ($queue !== null) {
            // The size and the age of the queue alone, never the person: to see queues that do not deliver.
            Log::info('attendance.offline_queue_reported', $queue);
        }

        AttachOfflineTickets::signedBy($person->id);

        return response()->json([])->header('Cache-Control', 'no-store, private');
    }
}
