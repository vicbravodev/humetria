<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\WorkerAccess\Contracts\WorkerSignatures;
use App\Modules\WorkerAccess\Enums\EntryVia;
use App\Modules\WorkerAccess\Http\Requests\EnterRequest;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * A person enters with their key and leaves. The challenge is given to this browser's session and is good only to enter; the
 * signature alone names the person (a key of the company's, in use, of a person still active). Every signature that does not
 * verify gets the same answer, whatever the cause. Logs carry ids only: never the credential or the challenge.
 */
final readonly class EntryController
{
    public function __construct(private WorkerSessionStore $sessions) {}

    public function options(Request $request, WorkerSignatures $signatures): JsonResponse
    {
        return response()->json($signatures->options(self::context($request)))->header('Cache-Control', 'no-store, private');
    }

    public function store(EnterRequest $request, string $tenant, WorkerSignatures $signatures): JsonResponse
    {
        $signed = $signatures->verify(self::context($request), $request->credential());

        if ($signed === null) {
            Log::warning('worker_access.entry_refused');

            return response()->json(['message' => 'No pudimos comprobar su llave. Intente de nuevo o pida un enlace por correo.'], 422)
                ->header('Cache-Control', 'no-store, private');
        }

        $this->sessions->start($signed->employeeId, EntryVia::Key, $signed->credentialId);
        Log::info('worker_access.entered', ['employee_id' => $signed->employeeId, 'via' => EntryVia::Key->value, 'key_id' => $signed->credentialId]);

        return response()->json(['redirect' => route('worker.home', $tenant)])->header('Cache-Control', 'no-store, private');
    }

    public function leave(string $tenant): RedirectResponse
    {
        $person = $this->sessions->current();
        $this->sessions->end();

        if ($person !== null) {
            Log::info('worker_access.left', ['employee_id' => $person->employeeId]);
        }

        return redirect()->route('worker.home', $tenant);
    }

    /** The challenges to enter are apart from those to mark, to read the week or to register a key. */
    private static function context(Request $request): string
    {
        return 'enter:'.$request->session()->getId();
    }
}
