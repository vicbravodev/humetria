<?php

declare(strict_types=1);

namespace App\Modules\Telework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Telework\Models\ChecklistApplication;
use App\Modules\Telework\Models\ChecklistPhoto;
use App\Modules\Telework\Models\EvidencePhoto;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Telework\Support\ReviewAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the NOM-037 evidence files (photos of a workplace, the signed visit authorization) from the private disk, to the
 * Comisión assigned to the person's work center and to RH. A viewer is refused (403); a file of another company or of
 * another center's Comisión does not exist (404). Nothing is cached: these are pictures of somebody's home.
 */
final class PhotoController extends Controller
{
    public function __construct(private readonly ReviewAccess $access, private readonly FileStore $files) {}

    public function checklist(Request $request, mixed $tenant, string $photo): StreamedResponse
    {
        $found = Str::isUuid($photo) ? ChecklistPhoto::query()->find($photo) : null;
        abort_if($found === null, 404);

        return $this->serve($request, ChecklistApplication::query()->findOrFail($found->application_id)->workplace_id, $found->path);
    }

    public function evidence(Request $request, mixed $tenant, string $photo): StreamedResponse
    {
        $found = Str::isUuid($photo) ? EvidencePhoto::query()->find($photo) : null;
        abort_if($found === null, 404);

        return $this->serve($request, EvidenceRequest::query()->findOrFail($found->evidence_request_id)->workplace_id, $found->path);
    }

    public function authorization(Request $request, mixed $tenant, string $authorization): StreamedResponse
    {
        $found = Str::isUuid($authorization) ? VisitAuthorization::query()->find($authorization) : null;
        abort_if($found === null || $found->document_path === null, 404);

        return $this->serve($request, $found->workplace_id, $found->document_path);
    }

    private function serve(Request $request, string $workplaceId, string $path): StreamedResponse
    {
        $user = $request->user();
        abort_if($user === null, 403);
        $this->access->reader($user->id, $this->access->workCenterOf(Workplace::query()->findOrFail($workplaceId)));

        $response = $this->files->stream($path);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
