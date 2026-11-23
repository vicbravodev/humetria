<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Jobs\SendPortalReminder;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\AckAudience;
use App\Modules\Telework\Support\AckAudienceIds;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Asks the people a document is for to acknowledge it (criterio C-20): a request row each, and an email to those who can
 * be reached. A version asks once per person (a unique index keeps it, and the rows go in by chunks that ignore the ones
 * already there), so asking again, or asking the people who arrive later, only adds those still missing. Acknowledgement
 * is per version: older versions' rows stay.
 */
final readonly class RequestAcknowledgements
{
    private const int CHUNK = 500;

    public function __construct(private CurrentTenant $tenant, private AckAudience $audience, private Roster $roster, private MailQuota $mail) {}

    /**
     * @param  list<string>  $documentIds
     * @param  bool  $retrying  a retry of a try that failed: whoever still waits is mailed too, not only who was just added.
     * @return int How many new requests were made.
     */
    public function handle(array $documentIds, bool $retrying = false): int
    {
        // A fresh get() is already a list.
        $documents = array_values(TeleworkDocument::query()->whereIn('id', $documentIds)->get()->all()); // @pest-mutate-ignore: UnwrapArrayValues

        return $this->ask($documents, fn (TeleworkDocument $document): AckAudienceIds => $this->audience->of($document), $retrying);
    }

    /** Asks one employee for the version in force of a kind in their work center: someone who arrived after it was published. */
    public function forEmployee(string $employeeId, string $workCenterId, DocumentKind $kind, bool $retrying = false): int
    {
        $document = $this->inForce($workCenterId, $kind);

        return $document === null ? 0 : $this->ask([$document], fn (): AckAudienceIds => new AckAudienceIds([$employeeId], []), $retrying);
    }

    /** Asks a Comisión member seated after the risk information was published, for the version in force of the center. */
    public function forUser(int $userId, string $workCenterId): int
    {
        $document = $this->inForce($workCenterId, DocumentKind::RiskInformation);

        return $document === null ? 0 : $this->ask([$document], fn (): AckAudienceIds => new AckAudienceIds([], [$userId]), false);
    }

    /**
     * Those who can be reached get an email (a Comisión member always can); an employee without an address is left to RH's record
     * of paper acknowledgements. The quota is held for them alone (the upper bound; the night gives back the rest) and, when it
     * does not fit, nobody is mailed and the requests stay.
     *
     * @param  list<string>  $documentIds
     * @param  list<string>  $employees
     * @param  list<int>  $users
     */
    private function tell(array $documentIds, array $employees, array $users): void
    {
        $reachable = [];

        foreach (array_chunk($employees, self::CHUNK) as $chunk) {
            $reachable = [...$reachable, ...array_keys(array_filter($this->roster->findMany($chunk), fn (RosterEntry $person): bool => $person->email !== null))];
        }

        if (! $this->mail->tryReserve(count($reachable) + count($users))) {
            Log::info('telework.acknowledgement_mail_withheld', ['document_ids' => $documentIds, 'employees' => count($reachable), 'users' => count($users)]);

            return;
        }

        $tenantId = $this->tenant->id();
        array_map(fn (string $id) => SendPortalReminder::dispatch($tenantId, $id, null)->delay(SendPortalReminder::DELAY), $reachable);
        array_map(fn (int $id) => SendPortalReminder::dispatch($tenantId, null, $id)->delay(SendPortalReminder::DELAY), $users);
    }

    private function inForce(string $workCenterId, DocumentKind $kind): ?TeleworkDocument
    {
        return TeleworkDocument::query()->where('work_center_id', $workCenterId)->where('kind', $kind)->orderByDesc('version')->first();
    }

    /**
     * @param  list<TeleworkDocument>  $documents
     * @param  callable(TeleworkDocument): AckAudienceIds  $audience
     */
    private function ask(array $documents, callable $audience, bool $retrying): int
    {
        // The request is always kept: the mail is a courtesy on top of it, and the quota only holds what is mailed.
        [$employees, $users, $everyone] = DB::transaction(function () use ($documents, $audience): array {
            $employees = [];
            $users = [];
            $everyone = new AckAudienceIds([], []);

            foreach ($documents as $document) {
                $ids = $audience($document);
                $employees = [...$employees, ...$this->insertMissing($document, 'employee_id', $ids->employeeIds)];
                $users = [...$users, ...$this->insertMissing($document, 'user_id', $ids->userIds)];
                $everyone = new AckAudienceIds([...$everyone->employeeIds, ...$ids->employeeIds], [...$everyone->userIds, ...$ids->userIds]);
            }

            // The people are only counted and chunked: their keys are never read.
            return [array_values(array_unique($employees)), array_values(array_unique($users)), $everyone]; // @pest-mutate-ignore: UnwrapArrayValues
        });

        if ($employees === [] && $users === [] && ! $retrying) {
            return 0;
        }

        $documentIds = array_map(fn (TeleworkDocument $document): string => $document->id, $documents);

        if ($employees !== [] || $users !== []) {
            Log::info('telework.acknowledgement_requested', ['document_ids' => $documentIds, 'employees' => count($employees), 'users' => count($users)]);
        }

        // Who was just added is told; on a retry, everyone of the audience still waiting: the try that failed may have kept the
        // requests and died before the mail was queued, and its retry finds them already made. A person has at most one reminder waiting (the job is unique).
        $this->tell($documentIds, $retrying ? $this->waiting($documentIds, 'employee_id', $everyone->employeeIds) : $employees, $retrying ? $this->waiting($documentIds, 'user_id', $everyone->userIds) : $users);

        return count($employees) + count($users);
    }

    /**
     * Who of `$people` has a request for any of the documents that is not confirmed yet.
     *
     * @template T of string|int
     *
     * @param  list<string>  $documentIds
     * @param  'employee_id'|'user_id'  $column
     * @param  list<T>  $people
     * @return list<T>
     */
    private function waiting(array $documentIds, string $column, array $people): array
    {
        $waiting = [];

        foreach (array_chunk(array_values(array_unique($people)), self::CHUNK) as $chunk) {
            $have = Acknowledgement::query()->whereIn('document_id', $documentIds)->whereIn($column, $chunk)->whereNull('acknowledged_at')->pluck($column)->all();
            $waiting = [...$waiting, ...array_values(array_filter($chunk, fn (string|int $person): bool => in_array($person, $have, false)))];
        }

        return $waiting;
    }

    /**
     * Inserts, by chunks, a request for each person who has none yet for the document, and says who they were.
     *
     * @template T of string|int
     *
     * @param  'employee_id'|'user_id'  $column
     * @param  list<T>  $people
     * @return list<T>
     */
    private function insertMissing(TeleworkDocument $document, string $column, array $people): array
    {
        $made = [];

        foreach (array_chunk($people, self::CHUNK) as $chunk) {
            $have = Acknowledgement::query()->where('document_id', $document->id)->whereIn($column, $chunk)->pluck($column)->all();
            // $have and $chunk hold the same type (uuid strings or user ids), so a strict in_array() reads the same; the keys
            // are never read (array_map() keeps them for insertOrIgnore() and the spread below renumbers them).
            $missing = array_values(array_filter($chunk, fn (string|int $person): bool => ! in_array($person, $have, false))); // @pest-mutate-ignore: FalseToTrue,UnwrapArrayValues
            $now = now();

            // The unique indexes make a race harmless: the row another request just made is skipped.
            DB::table('acknowledgements')->insertOrIgnore(array_map(fn (string|int $person): array => [
                // The query builder turns the uuid into its string itself: the cast only narrows the type.
                'id' => (string) Str::uuid(), // @pest-mutate-ignore: RemoveStringCast
                'tenant_id' => $document->tenant_id,
                'document_id' => $document->id,
                $column => $person,
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ], $missing));
            $made = [...$made, ...$missing];
        }

        return $made;
    }
}
