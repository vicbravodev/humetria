<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Jobs\SendAcknowledgementRequest;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\AckAudience;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Asks the people a document is for to acknowledge it (criterio C-20): a request row each, and an email to those who can
 * be reached. A version asks once per person (an index keeps it), so asking again, or asking the people who arrive
 * later, only adds those still missing. Acknowledgement is per version: older versions' rows stay.
 */
final readonly class RequestAcknowledgements
{
    public function __construct(private CurrentTenant $tenant, private AckAudience $audience, private Roster $roster) {}

    /**
     * @param  list<string>  $documentIds
     * @return int How many new requests were made.
     */
    public function handle(array $documentIds): int
    {
        $documents = TeleworkDocument::query()->whereIn('id', $documentIds)->get();

        return $this->ask(array_values($documents->all()), fn (TeleworkDocument $document): array => [$this->audience->employeeIds($document), $this->audience->userIds($document)]);
    }

    /** Asks one employee for the version in force of a kind in their work center: someone who arrived after it was published. */
    public function forEmployee(string $employeeId, string $workCenterId, DocumentKind $kind): int
    {
        $document = TeleworkDocument::query()->where('work_center_id', $workCenterId)->where('kind', $kind)->orderByDesc('version')->first();

        return $document === null ? 0 : $this->ask([$document], fn (): array => [[$employeeId], []]);
    }

    /**
     * @param  list<TeleworkDocument>  $documents
     * @param  callable(TeleworkDocument): array{0: list<string>, 1: list<int>}  $audience
     */
    private function ask(array $documents, callable $audience): int
    {
        $asked = DB::transaction(function () use ($documents, $audience): array {
            $employees = [];
            $users = [];

            foreach ($documents as $document) {
                [$employeeIds, $userIds] = $audience($document);

                foreach ($employeeIds as $id) {
                    $employees[] = $this->request($document, ['employee_id' => $id]) ? $id : null;
                }
                foreach ($userIds as $id) {
                    $users[] = $this->request($document, ['user_id' => $id]) ? $id : null;
                }
            }

            return [array_values(array_unique(array_filter($employees))), array_values(array_unique(array_filter($users)))];
        });
        [$employees, $users] = $asked;

        if ($employees === [] && $users === []) {
            return 0;
        }

        $tenantId = $this->tenant->id();
        // Those without an email are not mailed: RH records their paper acknowledgements.
        $reachable = array_keys(array_filter($this->roster->findMany($employees), fn (RosterEntry $person): bool => $person->email !== null));
        array_map(fn (string $id) => SendAcknowledgementRequest::dispatch($tenantId, $id, null), $reachable);
        array_map(fn (int $id) => SendAcknowledgementRequest::dispatch($tenantId, null, $id), $users);
        Log::info('telework.acknowledgement_requested', ['document_ids' => array_map(fn (TeleworkDocument $document): string => $document->id, $documents), 'employees' => count($employees), 'users' => count($users)]);

        return count($employees) + count($users);
    }

    /** @param array{employee_id: string}|array{user_id: int} $person */
    private function request(TeleworkDocument $document, array $person): bool
    {
        return Acknowledgement::query()->firstOrCreate(
            ['document_id' => $document->id, ...$person],
            ['tenant_id' => $document->tenant_id, 'requested_at' => now()],
        )->wasRecentlyCreated;
    }
}
