<?php

declare(strict_types=1);

namespace App\Modules\Demos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Demos\Actions\EndDemoSession;
use App\Modules\Demos\Actions\StartDemoRun;
use App\Modules\Demos\Actions\StartDemoSession;
use App\Modules\Demos\Contracts\DemoShowcase;
use App\Modules\Demos\Contracts\DoorControl;
use App\Modules\Demos\Http\Requests\DemoRoomRequest;
use App\Modules\Demos\Http\Requests\StartDemoSessionRequest;
use App\Modules\Demos\Support\DemoRoomPage;
use App\Modules\Demos\Support\QrCode;
use App\Modules\Tenancy\Contracts\DemoCompanies;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** «Sala de demostración»: prepare the demo, enter it and share it live. Links issued here show once, never stored in clear. */
final class DemoRoomController extends Controller
{
    /** A live session's length, in minutes. */
    private const int DOOR_MINUTES = 120;

    private const string UNQUEUED = 'No se pudo encolar la preparación: inténtalo de nuevo en unos minutos.';

    public function show(DemoRoomRequest $request, DemoRoomPage $page): Response
    {
        return Inertia::render('demos/room', $page->props());
    }

    public function prepare(DemoRoomRequest $request, StartDemoRun $start): RedirectResponse
    {
        return $this->started($start->handle('prepare'), 'Preparando la demo: tarda alrededor de medio minuto.');
    }

    public function reset(DemoRoomRequest $request, StartDemoRun $start): RedirectResponse
    {
        return $this->started($start->handle('reset'), 'Reiniciando la demo: tarda alrededor de medio minuto.');
    }

    public function startSession(StartDemoSessionRequest $request, StartDemoSession $start): RedirectResponse
    {
        Inertia::flash('toast', match ($start->handle($request->clientName(), $request->tours())) {
            'started' => ['type' => 'success', 'message' => 'Preparando la sesión: tarda alrededor de medio minuto.'],
            'busy' => ['type' => 'error', 'message' => 'Ya hay una sesión en curso o una preparación corriendo: espera a que termine.'],
            'unqueued' => ['type' => 'error', 'message' => self::UNQUEUED],
        });

        return back();
    }

    public function endSession(DemoRoomRequest $request, EndDemoSession $end): RedirectResponse
    {
        Inertia::flash('toast', match ($end->handle()) {
            'started' => ['type' => 'success', 'message' => 'Terminando la sesión: la demo se reinicia y borra lo que dejó el público.'],
            'busy' => ['type' => 'error', 'message' => 'Hay una preparación corriendo: espera a que termine para terminar la sesión.'],
            'unqueued' => ['type' => 'error', 'message' => self::UNQUEUED],
            'none' => ['type' => 'error', 'message' => 'No hay una sesión en curso.'],
        });

        return back();
    }

    public function openDoor(DemoRoomRequest $request, DoorControl $door, QrCode $qr): RedirectResponse
    {
        $url = $door->open(self::DOOR_MINUTES)['url'];
        Inertia::flash('doorLink', ['url' => $url, 'qr' => $qr->dataUri($url)]);

        return back();
    }

    public function closeDoor(DemoRoomRequest $request, DoorControl $door): RedirectResponse
    {
        $door->close();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Sala cerrada: su QR ya no deja entrar a nadie.']);

        return back();
    }

    public function reissueKiosk(DemoRoomRequest $request, DemoCompanies $demos, DemoShowcase $showcase, QrCode $qr): RedirectResponse
    {
        abort_if($demos->id() === null, 404);
        $links = $showcase->kioskLinks();

        if ($links === []) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Las pantallas del checador ya muestran el código. Para tener otros enlaces, reinicia la demo.']);
        } else {
            Inertia::flash('demoKioskLinks', array_map(fn (array $link): array => [...$link, 'qr' => $qr->dataUri($link['url'])], $links));
        }

        return back();
    }

    /** @param  'started'|'busy'|'unqueued'  $outcome */
    private function started(string $outcome, string $message): RedirectResponse
    {
        Inertia::flash('toast', match ($outcome) {
            'started' => ['type' => 'success', 'message' => $message],
            'busy' => ['type' => 'error', 'message' => 'Ya hay una preparación corriendo: espera a que termine.'],
            'unqueued' => ['type' => 'error', 'message' => self::UNQUEUED],
        });

        return back();
    }
}
