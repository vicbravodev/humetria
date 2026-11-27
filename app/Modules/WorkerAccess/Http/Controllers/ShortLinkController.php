<?php

declare(strict_types=1);

namespace App\Modules\WorkerAccess\Http\Controllers;

use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\WorkerAccess\Models\WorkerAccessLink;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * `access/{token}`, the address of a WhatsApp button (Meta takes the variable only at the end, and the link of a company has its
 * slug in the middle): finds the company by the token and sends the person to that link, which shows «Continuar» and spends
 * nothing. A link that would not open (unknown, used, revoked, expired) gets the screen of a link that does not work, without its
 * company: the answer does not say whether it ever existed.
 */
final readonly class ShortLinkController
{
    public function __construct(private Companies $companies) {}

    public function __invoke(string $token): RedirectResponse|Response
    {
        $link = WorkerAccessLink::findPendingInAnyCompany($token);

        // The link's page judges the rest, as for any link: a person who left, or a blocked company (its 404).
        if ($link !== null) {
            return redirect()->route('worker.link.show', [$this->companies->enter($link->tenant_id)->slug, $token]);
        }

        return Inertia::render('worker-access/link', ['company' => config()->string('app.name'), 'opens' => false, 'firstUse' => false, 'homeUrl' => route('home')]);
    }
}
