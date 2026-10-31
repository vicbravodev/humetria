<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Actions\SubmitNotice;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Telework\Http\Requests\NoticeRequest;
use App\Modules\Telework\Models\WorkerNotice;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Tenancy\Contracts\WorkCenters;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The person's three written notices from their portal (numerals 6.3, 6.7, 6.9): the forms and the list of what they
 * sent, which says only "Recibido" or "Revisado" (never what the Comisión wrote). Only a person with an open period.
 */
final class PortalNoticeController extends Controller
{
    public function show(string $token, OpenPortal $openPortal, WorkCenters $workCenters): Response
    {
        $portal = $this->portal($token, $openPortal);
        $zone = config()->string('app.display_timezone');

        return Inertia::render('telework/portal-notices', [
            'token' => $token,
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
                ]),
        ]);
    }

    public function store(string $token, NoticeRequest $request, OpenPortal $openPortal, SubmitNotice $submit): RedirectResponse
    {
        $portal = $this->portal($token, $openPortal);

        $submit->handle($portal, $request->kind(), $request->writtenOn(), $request->occurredOn(), $request->string('description')->toString(), $request->traumaticEvent(), $request->newAddress(), $request->temporary(), $request->ip());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Su aviso fue enviado. La empresa lo recibió.']);

        return to_route('portal.notices.show', $token);
    }

    private function portal(string $token, OpenPortal $openPortal): OpenedPortal
    {
        try {
            $portal = $openPortal->handle($token);
        } catch (PortalUnavailable) {
            abort(404);
        }

        abort_if($portal->teleworkerId === null, 404);

        return $portal;
    }
}
