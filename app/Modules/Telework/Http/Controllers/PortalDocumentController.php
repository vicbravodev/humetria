<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Actions\Acknowledge;
use App\Modules\Telework\Actions\OpenPortal;
use App\Modules\Telework\Exceptions\PortalUnavailable;
use App\Modules\Telework\Models\Acknowledgement;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Telework\Support\OpenedPortal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The documents a person was asked to confirm, from their portal: read the PDF and confirm it. Only a document that was
 * asked of that person exists for them; a link that opens nothing does not exist either.
 */
final class PortalDocumentController extends Controller
{
    public function file(string $token, string $document, OpenPortal $openPortal, FileStore $files): StreamedResponse
    {
        $portal = $this->portal($token, $openPortal);
        $asked = Acknowledgement::query()->where('employee_id', $portal->employeeId)->where('document_id', $document)->exists();
        $found = $asked ? TeleworkDocument::query()->find($document) : null;
        abort_if($found === null, 404);

        $response = $files->stream($found->path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function acknowledge(string $token, string $document, Request $request, OpenPortal $openPortal, Acknowledge $acknowledge): RedirectResponse
    {
        $acknowledge->handle($this->portal($token, $openPortal), $document, $request->ip());

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
