<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

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

    public function __construct(private CurrentTenant $tenant, private AckAudience $audience, private Roster $roster) {}

    /**
     * @param  list<string>  $documentIds
     * @return int How many new requests were made.
     */
    public function handle(array $documentIds): int
    {
        // A fresh get() is already a list.
        $documents = array_values(TeleworkDocument::query()->whereIn('id', $documentIds)->get()->all()); // @pest-mutate-ignore: UnwrapArrayValues

        return $this->ask($documents, fn (TeleworkDocument $document): AckAudienceIds => $this->audience->of($document));
    }

    /** Asks one employee for the version in force of a kind in their work center: someone who arrived after it was published. */
    public function forEmployee(string $employeeId, string $workCenterId, DocumentKind $kind): int
    {
        $document = $this->inForce($workCenterId, $kind);

        return $document === null ? 0 : $this->ask([$document], fn (): AckAudienceIds => new AckAudienceIds([$employeeId], []));
    }

    /** Asks a Comisión member seated after the risk information was published, for the version in force of the center. */
    public function forUser(int $userId, string $workCenterId): int
    {
        $document = $this->inForce($workCenterId, DocumentKind::RiskInformation);

        return $document === null ? 0 : $this->ask([$document], fn (): AckAudienceIds => new AckAudienceIds([], [$userId]));
    }

    private function inForce(string $workCenterId, DocumentKind $kind): ?TeleworkDocument
    {
        return TeleworkDocument::query()->where('work_center_id', $workCenterId)->where('kind', $kind)->orderByDesc('version')->first();
    }

    /**
     * @param  list<TeleworkDocument>  $documents
     * @param  callable(TeleworkDocument): AckAudienceIds  $audience
     */
    private function ask(array $documents, callable $audience): int
    {
        [$employees, $users] = DB::transaction(function () use ($documents, $audience): array {
            $employees = [];
            $users = [];

            foreach ($documents as $document) {
                $ids = $audience($document);
                $employees = [...$employees, ...$this->insertMissing($document, 'employee_id', $ids->employeeIds)];
                $users = [...$users, ...$this->insertMissing($document, 'user_id', $ids->userIds)];
            }

            return [array_values(array_unique($employees)), array_values(array_unique($users))];
        });

        if ($employees === [] && $users === []) {
            return 0;
        }

        $tenantId = $this->tenant->id();
        // Those without an email are not mailed: RH records their paper acknowledgements.
        foreach (array_chunk($employees, self::CHUNK) as $chunk) {
            $reachable = array_keys(array_filter($this->roster->findMany($chunk), fn (RosterEntry $person): bool => $person->email !== null));
            array_map(fn (string $id) => SendPortalReminder::dispatch($tenantId, $id, null)->delay(SendPortalReminder::DELAY), $reachable);
        }
        array_map(fn (int $id) => SendPortalReminder::dispatch($tenantId, null, $id)->delay(SendPortalReminder::DELAY), $users);
        Log::info('telework.acknowledgement_requested', ['document_ids' => array_map(fn (TeleworkDocument $document): string => $document->id, $documents), 'employees' => count($employees), 'users' => count($users)]);

        return count($employees) + count($users);
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
