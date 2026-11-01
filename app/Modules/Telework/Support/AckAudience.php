<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Enums\TeleworkerStatus;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Models\Teleworker;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Data\MemberSummary;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who has to acknowledge a document today (criterio C-20): the policy, every active employee of its center; the risk
 * information, the roster (padrón) of the center and its Comisión; the other kinds nobody. Worked out once per document
 * (`of`) and handed to whoever needs it.
 */
final readonly class AckAudience
{
    public function __construct(private Roster $roster, private Members $members) {}

    public function of(TeleworkDocument $document): AckAudienceIds
    {
        return new AckAudienceIds($this->employeeIds($document), $this->userIds($document));
    }

    /**
     * The audiences of several documents, in a fixed number of queries whatever their number: the people of all their
     * centers are read at once and split by center.
     *
     * @param  list<TeleworkDocument>  $documents
     * @return array<string, AckAudienceIds> by document id
     */
    public function ofMany(array $documents): array
    {
        // The centers only feed whereIn() queries, and each kind's audience reads only its own documents' centers: a center
        // of the other kind, a repeated one or the keys change no audience.
        $centers = fn (DocumentKind $kind): array => array_values(array_unique(array_map(fn (TeleworkDocument $document): string => $document->work_center_id, array_filter($documents, fn (TeleworkDocument $document): bool => $document->kind === $kind)))); // @pest-mutate-ignore: UnwrapArrayFilter,UnwrapArrayUnique,UnwrapArrayValues
        $policyCenters = $centers(DocumentKind::Policy);
        $riskCenters = $centers(DocumentKind::RiskInformation);

        $active = $policyCenters === [] ? [] : $this->roster->activeIdsInMany($policyCenters);
        $rostered = [];
        $seated = [];

        if ($riskCenters !== []) {
            foreach (Teleworker::query()->whereIn('work_center_id', $riskCenters)->where('status', TeleworkerStatus::Active)->get(['employee_id', 'work_center_id']) as $period) {
                $rostered[$period->work_center_id][] = $period->employee_id;
            }
            $reviewers = array_map(fn (MemberSummary $member): int => $member->userId, $this->members->reviewers());
            foreach (CommitteeAssignment::query()->whereIn('work_center_id', $riskCenters)->whereIn('user_id', $reviewers)->get(['user_id', 'work_center_id']) as $seat) {
                $seated[$seat->work_center_id][] = $seat->user_id;
            }
        }

        $audiences = [];
        foreach ($documents as $document) {
            $audiences[$document->id] = match ($document->kind) {
                DocumentKind::Policy => new AckAudienceIds($active[$document->work_center_id] ?? [], []),
                DocumentKind::RiskInformation => new AckAudienceIds($rostered[$document->work_center_id] ?? [], $seated[$document->work_center_id] ?? []),
                default => new AckAudienceIds([], []),
            };
        }

        return $audiences;
    }

    /**
     * The requests of a document that still count: those of the people expected today.
     *
     * @return Builder<Acknowledgement>
     */
    public function requests(TeleworkDocument $document, AckAudienceIds $audience): Builder
    {
        return Acknowledgement::query()->where('document_id', $document->id)
            ->where(fn (Builder $query) => $query->whereIn('employee_id', $audience->employeeIds)->orWhereIn('user_id', $audience->userIds));
    }

    /**
     * The employees still pending who match a search (name, email or number), by name: what "all the pending ones I found"
     * means, resolved here so it holds for any number of people.
     *
     * @return list<string>
     */
    public function pendingMatching(TeleworkDocument $document, AckAudienceIds $audience, string $search): array
    {
        // Read with whereNotNull('employee_id'), so none is null; the ids only feed a roster query.
        $pending = array_values(array_filter($this->requests($document, $audience)->whereNull('acknowledged_at')->whereNotNull('employee_id')->get(['employee_id'])->map(fn (Acknowledgement $ack): ?string => $ack->employee_id)->all(), is_string(...))); // @pest-mutate-ignore: UnwrapArrayFilter,UnwrapArrayValues

        return $this->roster->matchingIds(new RosterQuery(employeeIds: $pending, search: $search));
    }

    /** @return list<string> */
    private function employeeIds(TeleworkDocument $document): array
    {
        return match ($document->kind) {
            DocumentKind::Policy => $this->roster->activeIdsIn($document->work_center_id),
            // A fresh get() is already a list, here and in userIds().
            DocumentKind::RiskInformation => array_values(Teleworker::query() // @pest-mutate-ignore: UnwrapArrayValues
                ->where('work_center_id', $document->work_center_id)->where('status', TeleworkerStatus::Active)->get(['employee_id'])->map(fn (Teleworker $period): string => $period->employee_id)->all()),
            default => [],
        };
    }

    /** @return list<int> */
    private function userIds(TeleworkDocument $document): array
    {
        if ($document->kind !== DocumentKind::RiskInformation) {
            return [];
        }

        $reviewers = array_map(fn (MemberSummary $member): int => $member->userId, $this->members->reviewers());

        return array_values(CommitteeAssignment::query() // @pest-mutate-ignore: UnwrapArrayValues
            ->where('work_center_id', $document->work_center_id)->whereIn('user_id', $reviewers)->get(['user_id'])->map(fn (CommitteeAssignment $seat): int => $seat->user_id)->all());
    }
}
