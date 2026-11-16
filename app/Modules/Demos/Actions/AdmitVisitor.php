<?php

declare(strict_types=1);

namespace App\Modules\Demos\Actions;

use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Models\DoorAssignment;
use App\Modules\Demos\Models\DoorSession;
use App\Modules\Demos\Support\AdmissionResult;
use App\Modules\Demos\Support\DemoDoor;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Telework\Contracts\PortalAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lets a visitor of the demo in through the door's code: the worker they took before in this session (their cookie names
 * the assignment), or the next door person nobody took since the demo was prepared. Only active people of the demo company
 * are ever handed out. A code that is unknown, closed, expired or of another company is simply closed, never told apart.
 */
final readonly class AdmitVisitor
{
    public function __construct(private DemoDoor $door, private DemoDoorPool $pool, private Roster $roster, private PortalAccess $portals) {}

    /**
     * @param  string|null  $assignmentId  the visitor's cookie, already checked by its signature: null when absent or forged.
     */
    public function handle(string $code, ?string $assignmentId): AdmissionResult
    {
        // Entered first, whatever the code: every refusal then renders inside the same company and reads the same.
        if ($this->door->enter() === null || preg_match('/^[A-Za-z0-9]{24}$/', $code) !== 1) {
            return AdmissionResult::closed();
        }

        return DB::transaction(function () use ($code, $assignmentId): AdmissionResult {
            // Locked: two visitors scanning at once are handed out one after the other, never the same person.
            $session = DoorSession::query()->where('code_hash', hash('sha256', $code))->lockForUpdate()->first();

            if ($session === null || ! $session->isOpen()) {
                return AdmissionResult::closed();
            }

            $returning = $assignmentId !== null && Str::isUuid($assignmentId)
                ? DoorAssignment::query()->where('session_id', $session->id)->find($assignmentId)
                : null;

            if ($returning !== null) {
                $url = $this->portals->url($returning->employee_id);

                return $url === null ? AdmissionResult::closed() : AdmissionResult::admitted($returning->id, $returning->employee_id, $url, $session->expires_at);
            }

            return $this->claim($session);
        });
    }

    private function claim(DoorSession $session): AdmissionResult
    {
        $taken = DoorAssignment::query()->pluck('employee_id')->all();
        $free = array_values(array_filter($this->pool->employeeIds(), fn (string $id): bool => Str::isUuid($id) && ! in_array($id, $taken, true)));
        // Only people of the company entered, the demo: an id of anyone else is not found.
        $people = $this->roster->findMany($free);

        foreach ($free as $id) {
            $url = ($people[$id] ?? null)?->active === true ? $this->portals->url($id) : null;

            if ($url !== null) {
                $assignment = DoorAssignment::query()->create(['tenant_id' => $session->tenant_id, 'session_id' => $session->id, 'employee_id' => $id, 'claimed_at' => now()]);
                Log::info('demo.door_admitted', ['assignment_id' => $assignment->id]);

                return AdmissionResult::admitted($assignment->id, $id, $url, $session->expires_at);
            }
        }

        Log::info('demo.door_full', ['session_id' => $session->id]);

        return AdmissionResult::full();
    }
}
