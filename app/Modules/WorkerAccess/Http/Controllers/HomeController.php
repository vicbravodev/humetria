<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Support\KeyAllowance;
use App\Modules\WorkerAccess\Support\WorkerSessionStore;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The person's entrance, `me/{slug}`: with a session, their start; without one, the screen to enter, which names nobody
 * (whoever entered on this device before leaves no trace on it).
 */
final readonly class HomeController
{
    public function __construct(private Companies $companies, private WorkerSessionStore $sessions) {}

    public function __invoke(string $tenant): Response
    {
        $session = $this->sessions->open();

        if ($session === null) {
            return Inertia::render('worker-access/enter', [
                'company' => $this->companies->current()->tradeName,
                'optionsUrl' => route('worker.enter.options', $tenant),
                'enterUrl' => route('worker.enter', $tenant),
                'linkUrl' => route('worker.link.request', $tenant),
            ]);
        }

        return Inertia::render('worker-access/home', [
            'company' => $session->identity->companyName,
            'name' => $session->identity->personName,
            'keyUrl' => KeyAllowance::refusal($session) === null ? route('worker.key.show', $tenant) : null,
            'leaveUrl' => route('worker.leave', $tenant),
        ]);
    }
}
