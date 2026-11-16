<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\WorkerAccess\Actions\RegisterKey;
use App\Modules\WorkerAccess\Contracts\WorkerKeys;
use App\Modules\WorkerAccess\Exceptions\TooManyKeys;
use App\Modules\WorkerAccess\Http\Requests\RegisterKeyRequest;
use App\Modules\WorkerAccess\Support\KeyAllowance;
use App\Modules\WorkerAccess\Support\OpenSession;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A person registers a key on their device, in their session (decision 3): with a link RH issued or with a key they have;
 * never with a link they asked for, and up to 3 keys in use. Without a session, the page sends them to enter; the options and
 * the key answer 403 for a session that may not, and 409 when the person has the most keys.
 */
final readonly class KeyController
{
    public function __construct(private WorkerSessionStore $sessions) {}

    public function show(string $tenant): Response|RedirectResponse
    {
        $session = $this->sessions->open();

        if ($session === null) {
            return redirect()->route('worker.home', $tenant);
        }

        $refusal = KeyAllowance::refusal($session);

        return Inertia::render('worker-access/key', [
            'company' => $session->identity->companyName,
            'name' => $session->identity->personName,
            'blocked' => $refusal === 'full' ? 'full' : ($refusal === null ? null : 'forbidden'),
            'optionsUrl' => route('worker.key.options', $tenant),
            'storeUrl' => route('worker.key.store', $tenant),
            'homeUrl' => route('worker.home', $tenant),
        ]);
    }

    public function options(WorkerKeys $keys): JsonResponse
    {
        $session = $this->allowed();

        return response()->json($keys->registrationOptions($session->identity->employeeId, $session->identity->personName))
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(RegisterKeyRequest $request, string $tenant, WorkerKeys $keys, RegisterKey $register): JsonResponse
    {
        $session = $this->allowed();
        $person = $session->identity->employeeId;
        $key = $keys->verifyRegistration($person, $request->credential());

        try {
            $saved = $key === null ? null : $register->handle($person, $key, $request->string('label')->toString(), $session->via);
        } catch (TooManyKeys) {
            abort(409);
        }

        if ($saved === null) {
            return response()->json(['message' => 'No se pudo guardar la llave. Intente de nuevo.'], 422);
        }

        return response()->json(['redirect' => route('worker.home', $tenant)])->header('Cache-Control', 'no-store, private');
    }

    /** The open session, when it may register a key; 403 without one or when it may not, 409 with the most keys. */
    private function allowed(): OpenSession
    {
        $session = $this->sessions->open();
        abort_if($session === null, 403);
        $refusal = KeyAllowance::refusal($session);
        abort_if($refusal === 'full', 409);
        abort_if($refusal !== null, 403);

        return $session;
    }
}
