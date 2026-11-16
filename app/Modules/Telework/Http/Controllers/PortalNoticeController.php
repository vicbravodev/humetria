<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\SubmitNotice;
use App\Modules\Telework\Enums\NoticeAction;
use App\Modules\Telework\Http\Requests\NoticeRequest;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\SessionPortal;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The person's three written notices from their portal (numerals 6.3, 6.7, 6.9): the forms and the list of what they
 * sent, which says "Recibido" or "Revisado" and, for an accident, what was done about it (rediseño v2, board V3-Aviso);
 * never a note written for RH only. Only a person with an open period.
 */
final class PortalNoticeController extends Controller
{
    public function show(string $tenant, SessionPortal $portals, WorkCenters $workCenters): Response
    {
        $portal = $this->portal($portals);
        $zone = config()->string('app.display_timezone');

        return Inertia::render('telework/portal-notices', [
            'slug' => $tenant,
            'company' => $portal->company,
            'name' => $portal->name,
            'states' => $workCenters->states(),
            'today' => CarbonImmutable::now($zone)->toDateString(),
            'notices' => WorkerNotice::query()->where('teleworker_id', $portal->teleworkerId)->orderByDesc('submitted_at')->orderBy('id')->paginate(10)->withQueryString()
                ->through(fn (WorkerNotice $notice): array => [
                    'id' => $notice->id,
                    'kind' => $notice->kind->label(),
                    'writtenOn' => $notice->written_on->toDateString(),
                    'status' => $notice->reviewed_at === null ? 'Recibido' : 'Revisado',
                    // An accident answered with what was done (board V3-Aviso); a note written before that was for RH only.
                    'answer' => $notice->review_actions === null || $notice->reviewed_at === null ? null : [
                        'actions' => array_map(fn (string $action): string => NoticeAction::from($action)->label(), $notice->review_actions),
                        'note' => $notice->review_note,
                        'answeredOn' => $notice->reviewed_at->setTimezone($zone)->toDateString(),
                    ],
                ]),
        ]);
    }

    public function store(string $tenant, NoticeRequest $request, SessionPortal $portals, SubmitNotice $submit): RedirectResponse
    {
        $portal = $this->portal($portals);

        $submit->handle($portal, $request->kind(), $request->writtenOn(), $request->occurredOn(), $request->string('description')->toString(), $request->traumaticEvent(), $request->newAddress(), $request->temporary(), $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Su aviso fue enviado. La empresa lo recibió.']);

        return to_route('portal.notices.show', $tenant);
    }

    private function portal(SessionPortal $portals): OpenedPortal
    {
        $portal = $portals->current() ?? abort(404);
        abort_if($portal->teleworkerId === null, 404);

        return $portal;
    }
}
