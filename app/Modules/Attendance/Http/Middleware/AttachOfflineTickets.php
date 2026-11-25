<?php

declare(strict_types=1);

namespace App\Modules\Attendance\Http\Middleware;

use App\Modules\Attendance\Support\OfflineTicket;
use App\Modules\Organization\Contracts\Roster;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hands a new batch of tickets and the context to the phone that just proved who it is (criterio 3.3.1): every JSON answer
 * after a signature that verified (mark, week, correction, agreement, tickets) gets `tickets` and `context` at the root of
 * its body, whether it is a 200 or the 409 or 422 that follows the signature. A controller says who signed by setting
 * `SIGNED` on the request once it verified the signature and found the person; an answer without it (a signature that did
 * not verify, a refusal that hides a record) carries nothing, nor does a 404 even after it.
 */
final readonly class AttachOfflineTickets
{
    public const string SIGNED = 'attendance.signed_employee';

    /** What follows a verified signature and may carry the tickets: the answer, a stale agreement, a refusal. */
    private const array STATUSES = [200, 409, 422];

    public function __construct(private OfflineTicket $tickets, private Roster $roster) {}

    /**
     * A controller says who signed. The request is the one this middleware sees, not the controller's form request: that
     * one is a copy (`createFrom` copies the attributes), so what is set on it never reaches the middleware.
     */
    public static function signedBy(string $employeeId): void
    {
        request()->attributes->set(self::SIGNED, $employeeId);
    }

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $employeeId = $request->attributes->get(self::SIGNED);
        $body = $response instanceof JsonResponse ? $response->getData(true) : null;

        if (! is_string($employeeId) || ! is_array($body) || ! in_array($response->getStatusCode(), self::STATUSES, true)) {
            return $response;
        }

        $person = $this->roster->find($employeeId);

        if ($person === null || ! $person->active) {
            return $response;
        }

        return $response->setData([...$body, 'tickets' => $this->tickets->batch($person->id), 'context' => $this->tickets->context($person)]);
    }
}
