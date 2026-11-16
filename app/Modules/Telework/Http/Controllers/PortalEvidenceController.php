<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\ConfirmVisitAuthorization;
use App\Modules\Telework\Actions\FulfillPhotoRequest;
use App\Modules\Telework\Http\Requests\EvidencePhotosRequest;
use App\Modules\Telework\Support\PhotoStore;
use App\Modules\Telework\Support\SessionPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What the Comisión asks of a person, answered from their portal: photos of the workplace and the written authorization
 * of a visit. A request that is not theirs does not exist.
 */
final class PortalEvidenceController extends Controller
{
    public function photos(string $tenant, string $evidenceRequest, EvidencePhotosRequest $request, SessionPortal $portals, FulfillPhotoRequest $fulfil, PhotoStore $photos): RedirectResponse
    {
        $portal = $portals->current() ?? abort(404);
        // Before storing: a refused send must not take the company's quota.
        $fulfil->ensureOpen($portal, $evidenceRequest);
        $stored = $photos->storeMany(['general' => $request->photos()], "evidence/{$portal->tenantId}/{$evidenceRequest}");

        $fulfil->handle($portal, $evidenceRequest, $stored['general'] ?? []);

        return to_route('portal.telework.show', $tenant);
    }

    public function confirmVisit(string $tenant, string $authorization, Request $request, SessionPortal $portals, ConfirmVisitAuthorization $confirm): RedirectResponse
    {
        $confirm->handle($portals->current() ?? abort(404), $authorization, $request->ip());

        return to_route('portal.telework.show', $tenant);
    }
}
