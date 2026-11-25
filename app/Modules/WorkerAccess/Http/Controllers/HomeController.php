<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Contracts\WorkerDays;
use App\Modules\WorkerAccess\Data\WorkerSectionLink;
use App\Modules\WorkerAccess\Support\KeyAllowance;
use App\Modules\WorkerAccess\Support\ProofSteps;
use App\Modules\WorkerAccess\Support\WorkerSections;
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

    public function __invoke(string $tenant, WorkerSections $sections, ProofSteps $steps, WorkerDays $days): Response
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

        $day = $days->of($session->identity);
        $keys = KeyAllowance::inUse($session->identity->employeeId);

        return Inertia::render('worker-access/home', [
            'company' => $session->identity->companyName,
            'name' => $session->identity->personName,
            'slug' => $tenant,
            'sections' => array_map(fn (WorkerSectionLink $link): array => ['key' => $link->key, 'label' => $link->label, 'description' => $link->description, 'href' => $link->href, 'pending' => $link->pending], $sections->for($session->identity)),
            // Their own day (marks and what comes next), where the company keeps one: the start opens on the moment they are in.
            'day' => $day === null ? null : ['center' => $day->center, 'marks' => $day->marks, 'next' => $day->next],
            'keyUrl' => KeyAllowance::refusal($session, $keys) === null ? route('worker.key.show', $tenant) : null,
            'hasKey' => $keys > 0,
            // The device key of the app (plan B of the offline marks) only serves to mark: a company without attendance has none.
            'deviceKey' => $day !== null ? [
                'optionsUrl' => route('worker.device-key.options', $tenant),
                'storeUrl' => route('worker.device-key.store', $tenant),
                'proof' => $steps->for($session, $tenant),
            ] : null,
            // Marcar is a tab of the bar only where the company has attendance.
            'punchUrl' => $day !== null ? route('attendance.device.punch.show', $tenant) : null,
            'leaveUrl' => route('worker.leave', $tenant),
        ]);
    }
}
