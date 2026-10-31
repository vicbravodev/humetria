<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Contracts\Roster;
use App\Modules\Organization\Data\RosterEntry;
use App\Modules\Organization\Data\RosterQuery;
use App\Modules\Telework\Actions\Acknowledge;
use App\Modules\Telework\Actions\RecordManualAcknowledgements;
use App\Modules\Telework\Http\Requests\ManualAcknowledgementsRequest;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\AckAudience;
use App\Modules\Telework\Support\AckAudienceIds;
use App\Modules\Telework\Support\AckProgress;
use App\Modules\Telework\Support\VisibleCenters;
use App\Modules\Tenancy\Contracts\Members;
use App\Modules\Tenancy\Contracts\WorkCenters;
use App\Modules\Tenancy\Data\MemberSummary;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Who has confirmed a document and who has not (RH, the Comisión within its centers), the paper acknowledgements RH
 * records in bulk, and a Comisión member's own confirmation in the panel. A document outside reach is a 404.
 */
final class AcknowledgementController extends Controller
{
    private const array STATUSES = ['all', 'pending', 'acknowledged'];

    public function __construct(private readonly VisibleCenters $visible, private readonly AckAudience $audience, private readonly Roster $roster) {}

    public function index(Request $request, mixed $tenant, string $document, AckProgress $progress, WorkCenters $workCenters, Members $members): Response
    {
        Gate::authorize('view-telework');
        abort_if($request->user() === null, 403);
        $found = $this->reachable($request->user()->id, $document);
        $status = in_array($request->string('status')->toString(), self::STATUSES, true) ? $request->string('status')->toString() : 'all';
        $search = $request->string('q')->trim()->toString();

        $audience = $this->audience->of($found);
        $requests = $this->audience->requests($found, $audience)
            ->when($status === 'pending', fn ($query) => $query->whereNull('acknowledged_at'))
            ->when($status === 'acknowledged', fn ($query) => $query->whereNotNull('acknowledged_at'));
        $employeeIds = array_values(array_filter($requests->pluck('employee_id')->all(), is_string(...)));
        $query = new RosterQuery(employeeIds: $employeeIds, search: $search);
        $statusOf = fn (array $ids) => Acknowledgement::query()->where('document_id', $found->id)->whereIn('employee_id', $ids)->get()->keyBy('employee_id');

        $people = $this->roster->page($query, 15);
        $given = $statusOf(array_map(fn (RosterEntry $person): string => $person->id, $people->items()));
        $zone = config()->string('app.display_timezone');
        $date = fn (?CarbonImmutable $at): ?string => $at?->setTimezone($zone)->toDateString();

        return Inertia::render('telework/acknowledgements', [
            'document' => [
                'id' => $found->id,
                'title' => "{$found->kind->label()} · versión {$found->version}",
                'workCenter' => $workCenters->profile($found->work_center_id)->name ?? '',
                'publishedOn' => $date($found->created_at),
            ],
            'progress' => $progress->of($found, $audience),
            'people' => $people->through(function (RosterEntry $person) use ($given, $date): array {
                $ack = $given->get($person->id);

                return [
                    'id' => $person->id,
                    'employeeId' => $person->id,
                    'name' => $person->name,
                    'jobTitle' => $person->jobTitle,
                    'status' => $ack?->acknowledged_at === null ? 'pending' : 'acknowledged',
                    'channel' => $ack?->channel?->value,
                    'channelLabel' => $ack?->channel?->label(),
                    'acknowledgedOn' => $date($ack?->acknowledged_at),
                ];
            }),
            'committee' => $this->committee($found, $audience, $members, $status, $search, $date),
            'counts' => $this->counts($found, $audience),
            'filters' => ['status' => $status, 'q' => $search],
            'canManage' => Gate::allows('manage-telework'),
            'canAcknowledge' => Acknowledgement::query()->where('document_id', $found->id)->where('user_id', $request->user()->id)->whereNull('acknowledged_at')->exists(),
        ]);
    }

    public function acknowledge(Request $request, mixed $tenant, string $document, Acknowledge $acknowledge): RedirectResponse
    {
        Gate::authorize('view-telework');
        abort_if($request->user() === null || ! Str::isUuid($document), 404);

        $acknowledge->inPanel($request->user()->id, $document, $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Listo, quedó tu confirmación.']);

        return redirect()->route('tenant.telework.documents.index', $tenant);
    }

    public function store(ManualAcknowledgementsRequest $request, mixed $tenant, string $document, RecordManualAcknowledgements $record): RedirectResponse
    {
        $found = Str::isUuid($document) ? TeleworkDocument::query()->find($document) : null;
        abort_if($request->user() === null || $found === null, 404);

        // "All the pending ones the search found" is worked out here, so it holds above the cap on ticked ids.
        $ids = $request->allMatching() ? $this->audience->pendingMatching($found, $this->audience->of($found), $request->search()) : $request->employeeIds();
        $count = $record->handle($document, $ids, $request->signedOn(), $request->note(), $request->user()->id);
        Inertia::flash('toast', ['type' => 'success', 'message' => $count === 1 ? 'Se registró 1 acuse.' : "Se registraron {$count} acuses."]);

        return redirect()->route('tenant.telework.documents.acknowledgements.index', [$tenant, $document]);
    }

    /**
     * The committee members expected for the document, who confirm in the panel: a handful, so they travel as a short list.
     *
     * @param  callable(?CarbonImmutable): ?string  $date
     * @return list<array{name: string, status: string, acknowledgedOn: string|null}>
     */
    private function committee(TeleworkDocument $document, AckAudienceIds $audience, Members $members, string $status, string $search, callable $date): array
    {
        $needle = Str::lower(Str::ascii($search));
        $rows = [];

        foreach ($this->audience->requests($document, $audience)->whereNotNull('user_id')->get() as $ack) {
            $member = $ack->user_id === null ? null : $members->find($ack->user_id);
            $state = $ack->acknowledged_at === null ? 'pending' : 'acknowledged';

            if ($member instanceof MemberSummary && ($status === 'all' || $status === $state) && str_contains(Str::lower(Str::ascii($member->name)), $needle)) {
                $rows[] = ['name' => $member->name, 'status' => $state, 'acknowledgedOn' => $date($ack->acknowledged_at)];
            }
        }

        usort($rows, fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $rows;
    }

    /** @return array{all: int, pending: int, acknowledged: int} The employees' requests by state, for the tabs. */
    private function counts(TeleworkDocument $document, AckAudienceIds $audience): array
    {
        $all = $this->audience->requests($document, $audience)->whereNotNull('employee_id')->count();
        $done = $this->audience->requests($document, $audience)->whereNotNull('employee_id')->whereNotNull('acknowledged_at')->count();

        return ['all' => $all, 'pending' => $all - $done, 'acknowledged' => $done];
    }

    private function reachable(int $userId, string $document): TeleworkDocument
    {
        $found = Str::isUuid($document) ? TeleworkDocument::query()->find($document) : null;
        $limit = $this->visible->limitedTo($userId);
        abort_if($found === null || ($limit !== null && ! in_array($found->work_center_id, $limit, true)), 404);

        return $found;
    }
}
