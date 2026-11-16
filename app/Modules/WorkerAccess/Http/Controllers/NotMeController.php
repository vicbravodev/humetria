<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Actions\DisownKey;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «No fui yo», from the email of a new key, on an address the app signed with the key's id (a missing or altered signature gets
 * a 403, a key of another company a 404). Opening it asks first; confirming revokes the key and ends the person's sessions.
 */
final readonly class NotMeController
{
    public function show(Request $request, string $tenant, string $credential, Companies $companies): Response
    {
        $key = WorkerCredential::query()->findOrFail($credential);

        return Inertia::render('worker-access/not-me', [
            'company' => $companies->current()->tradeName,
            'label' => $key->label,
            'confirmUrl' => $key->revoked_reason === 'not_me' ? null : $request->fullUrl(),
        ]);
    }

    public function confirm(Request $request, string $tenant, string $credential, DisownKey $disown): RedirectResponse
    {
        $disown->handle($credential);

        return redirect()->to($request->fullUrl());
    }
}
