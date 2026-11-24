<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Actions;

use App\Modules\Billing\Contracts\MailQuota;
use App\Modules\Disclosure\Enums\DocumentKind;
use App\Modules\Disclosure\Enums\DocumentStatus;
use App\Modules\Disclosure\Jobs\SendPolicyReminder;
use App\Modules\Disclosure\Models\Acknowledgment;
use App\Modules\Disclosure\Models\Document;
use App\Modules\Disclosure\Models\DocumentCenter;
use App\Modules\Disclosure\Support\PolicyStanding;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Tenancy\Contracts\CurrentTenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Asks the people a version of the policy is for to confirm they read it (criterio C-53): one request row each, for the
 * active people of the centers it reached. A version asks once per person (a unique index keeps it and the rows go in by
 * chunks that ignore the ones already there), so asking again, or asking who arrives later, only adds those still missing.
 * Older versions' rows stay. A retry of a try that failed also mails whoever still waits: it may have failed after keeping the
 * requests, before the mail was queued. The pattern is the one of NOM-037's acknowledgements (Telework\Actions\RequestAcknowledgements),
 * copied with care and not extracted: when a third module needs «version × audience by center», one is designed for all.
 */
final readonly class RequestAcknowledgments
{
    private const int CHUNK = 500;

    public function __construct(private CurrentTenant $tenant, private Roster $roster, private PolicyStanding $standing, private MailQuota $mail) {}

    /**
     * @return int How many new requests were made.
     *
     * @throws ModelNotFoundException when the version does not exist, is a draft or is another company's.
     */
    public function handle(string $documentId, bool $retrying = false): int
    {
        $document = Document::query()->where('kind', DocumentKind::Policy)->where('status', DocumentStatus::Published)->findOrFail($documentId);
        $centers = array_values(DocumentCenter::query()->where('document_id', $document->id)->get(['work_center_id'])->map(fn (DocumentCenter $reach): string => $reach->work_center_id)->all());
        $audience = array_values(array_unique(array_merge([], ...array_values($this->roster->activeIdsInMany($centers)))));

        return $this->ask($document->id, $audience, $retrying);
    }

    /** Asks one person for the version in force of their work center: someone who arrived after it was published. */
    public function forEmployee(string $employeeId, string $workCenterId, bool $retrying = false): int
    {
        $inForce = $this->standing->inForce([$workCenterId])[$workCenterId] ?? null;

        return $inForce === null ? 0 : $this->ask($inForce->documentId, [$employeeId], $retrying);
    }

    /**
     * @param  list<string>  $employees
     */
    private function ask(string $documentId, array $employees, bool $retrying): int
    {
        // The request is always kept: the mail is a courtesy on top of it, and the quota only holds what is mailed (see tell()).
        $made = DB::transaction(function () use ($documentId, $employees): array {
            $made = [];

            foreach (array_chunk($employees, self::CHUNK) as $chunk) {
                $have = Acknowledgment::query()->where('document_id', $documentId)->whereIn('employee_id', $chunk)->get(['employee_id'])->map(fn (Acknowledgment $ack): string => $ack->employee_id)->all();
                $missing = array_values(array_diff($chunk, $have));
                $now = now();

                // The unique index makes a race harmless: the row another request just made is skipped.
                DB::table('disclosure_acknowledgments')->insertOrIgnore(array_map(fn (string $employee): array => [
                    'id' => Str::uuid()->toString(),
                    'tenant_id' => $this->tenant->id(),
                    'document_id' => $documentId,
                    'employee_id' => $employee,
                    'requested_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $missing));
                $made = [...$made, ...$missing];
            }

            return $made;
        });

        if ($made !== []) {
            Log::info('disclosure.acknowledgment_requested', ['document_id' => $documentId, 'employees' => count($made)]);
        }

        // Who was just added is told; on a retry, everyone of the audience still waiting: the try that failed may have kept the
        // requests and died before the mail was queued, and its retry finds them already made. A person has at most one reminder waiting (the job is unique).
        $this->tell($documentId, $retrying ? $employees : $made);

        return count($made);
    }

    /**
     * Those who can be reached get an email; the rest are left to RH's record of paper acknowledgments. The quota is held for
     * them alone (the upper bound; the night gives back the rest) and, when it does not fit, nobody is mailed and the requests stay.
     *
     * @param  list<string>  $employees
     */
    private function tell(string $documentId, array $employees): void
    {
        $reachable = [];

        foreach (array_chunk($employees, self::CHUNK) as $chunk) {
            $waiting = array_values(Acknowledgment::query()->where('document_id', $documentId)->whereIn('employee_id', $chunk)->whereNull('acknowledged_at')->get(['employee_id'])->map(fn (Acknowledgment $ack): string => $ack->employee_id)->all());
            $reachable = [...$reachable, ...array_keys(array_filter($this->roster->findMany($waiting), fn (RosterEntry $person): bool => $person->email !== null))];
        }

        if ($reachable === []) {
            return;
        }

        if (! $this->mail->tryReserve(count($reachable))) {
            Log::info('disclosure.acknowledgment_mail_withheld', ['document_id' => $documentId, 'employees' => count($reachable)]);

            return;
        }

        array_map(fn (string $id) => SendPolicyReminder::remind($this->tenant->id(), $id), $reachable);
    }
}
