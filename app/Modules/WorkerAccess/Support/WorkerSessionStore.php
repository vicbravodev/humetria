<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerSession;
use App\Modules\WorkerAccess\Data\WorkerIdentity;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Models\WorkerAccessState;
use Carbon\CarbonImmutable;

/**
 * The person's session, kept as the `worker` entry of Laravel's session (decision 4 of the spec): the only class that reads or
 * writes it. It holds only while all of this does: it is of the company of the request, it was used in the last 30 minutes
 * and opened in the last 8 hours (config/worker_access.php), it opened after the person's `valid_after`, the person is still
 * active and the company is not blocked. Otherwise the entry is forgotten and the request goes on as a visitor's. Every
 * request it holds for moves its last use on. Entering and leaving change the session id; leaving forgets only this entry.
 */
final readonly class WorkerSessionStore implements WorkerSession
{
    private const string KEY = 'worker';

    public function __construct(private Companies $companies, private Roster $roster) {}

    public function current(): ?WorkerIdentity
    {
        return $this->open()?->identity;
    }

    public function open(): ?OpenSession
    {
        $entry = session()->get(self::KEY);

        if (! is_array($entry)) {
            return null;
        }

        $session = $this->holding($entry);

        if ($session === null) {
            session()->forget(self::KEY);

            return null;
        }

        session()->put(self::KEY.'.seen_at', self::stamp(CarbonImmutable::now()));

        return $session;
    }

    /** Opens the session of a person of the current company, with a new session id. */
    public function start(string $employeeId, EntryVia $via, ?string $credentialId = null): void
    {
        $now = self::stamp(CarbonImmutable::now());

        session()->regenerate();
        request()->attributes->remove(RequestWorkerSession::ATTRIBUTE);
        session()->put(self::KEY, [
            'tenant_id' => $this->companies->current()->id,
            'employee_id' => $employeeId,
            'entered_at' => $now,
            'seen_at' => $now,
            'via' => $via->value,
            'credential_id' => $credentialId,
            // A key's signature is a recent proof; a link is not.
            'proof_at' => $via === EntryVia::Key ? $now : null,
        ]);
    }

    /** Leaves: forgets only the person's entry, with a new session id. */
    public function end(): void
    {
        session()->forget(self::KEY);
        session()->regenerate();
        request()->attributes->remove(RequestWorkerSession::ATTRIBUTE);
    }

    /**
     * @param  array<mixed>  $entry
     */
    private function holding(array $entry): ?OpenSession
    {
        $company = $this->companies->current();
        $employeeId = $entry['employee_id'] ?? null;
        $via = EntryVia::tryFrom(is_string($entry['via'] ?? null) ? $entry['via'] : '');
        $enteredAt = self::time($entry['entered_at'] ?? null);
        $seenAt = self::time($entry['seen_at'] ?? null);
        $now = CarbonImmutable::now();

        if (($entry['tenant_id'] ?? null) !== $company->id || $company->blocked || ! is_string($employeeId) || $via === null || $enteredAt === null || $seenAt === null
            || $seenAt->addMinutes(config()->integer('worker_access.session.idle_minutes'))->lt($now)
            || $enteredAt->addHours(config()->integer('worker_access.session.absolute_hours'))->lt($now)) {
            return null;
        }

        $validAfter = WorkerAccessState::query()->where('employee_id', $employeeId)->value('valid_after');
        $person = $this->roster->find($employeeId);

        if (($validAfter instanceof CarbonImmutable && $enteredAt->lte($validAfter)) || $person === null || ! $person->active) {
            return null;
        }

        $credentialId = $entry['credential_id'] ?? null;

        return new OpenSession(new WorkerIdentity($company->id, $person->id, $company->tradeName, $person->name), $via, is_string($credentialId) ? $credentialId : null, self::time($entry['proof_at'] ?? null));
    }

    private static function stamp(CarbonImmutable $time): string
    {
        return $time->format('Y-m-d\TH:i:s.uP');
    }

    private static function time(mixed $value): ?CarbonImmutable
    {
        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }
}
