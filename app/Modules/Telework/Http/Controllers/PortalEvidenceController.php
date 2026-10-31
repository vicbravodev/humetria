<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\ConfirmVisitAuthorization;
use App\Modules\Telework\Actions\FulfillPhotoRequest;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Telework\Http\Requests\EvidencePhotosRequest;
use App\Modules\Telework\Support\OpenedPortal;
use App\Modules\Telework\Support\PhotoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * What the Comisión asks of a person, answered from their portal: photos of the workplace and the written authorization
 * of a visit. A link that opens nothing, or a request that is not theirs, does not exist.
 */
final class PortalEvidenceController extends Controller
{
    public function photos(string $token, string $evidenceRequest, EvidencePhotosRequest $request, OpenPortal $openPortal, FulfillPhotoRequest $fulfil, PhotoStore $photos): RedirectResponse
    {
        $portal = $this->portal($token, $openPortal);
        $stored = $photos->storeMany(['general' => $request->photos()], "evidence/{$portal->tenantId}/{$evidenceRequest}");

        $fulfil->handle($portal, $evidenceRequest, $stored['general'] ?? []);

        return to_route('portal.show', $token);
    }

    public function confirmVisit(string $token, string $authorization, Request $request, OpenPortal $openPortal, ConfirmVisitAuthorization $confirm): RedirectResponse
    {
        $confirm->handle($this->portal($token, $openPortal), $authorization, (string) $request->ip());

        return to_route('portal.show', $token);
    }

    private function portal(string $token, OpenPortal $openPortal): OpenedPortal
    {
        try {
            return $openPortal->handle($token);
        } catch (PortalUnavailable) {
            abort(404);
        }
    }
}
