<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Enums\EvidenceKind;
use App\Modules\Telework\Models\EvidenceRequest;
use App\Modules\Telework\Models\Workplace;
use App\Modules\Telework\Support\FileStore;
use App\Modules\Telework\Support\OpenedPortal;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The person sends the photos the Comisión asked for. The request closes with them; it cannot be answered twice. The
 * photos arrive already stored (`PhotoStore`): whatever happens, a send that is not saved leaves none of them behind.
 */
final readonly class FulfillPhotoRequest
{
    public function __construct(private FileStore $files) {}

    /**
     * @param  list<string>  $paths  Stored photos, at least one.
     *
     * @throws ModelNotFoundException when the request is not a photo request of one of this person's workplaces.
     * @throws ValidationException when the request was already answered.
     */
    public function handle(OpenedPortal $portal, string $requestId, array $paths): void
    {
        try {
            DB::transaction(fn () => $this->fulfil($portal, $requestId, $paths));
        } catch (Throwable $failure) {
            $this->files->delete(...$paths);

            throw $failure;
        }
    }

    /**
     * @param  list<string>  $paths
     */
    private function fulfil(OpenedPortal $portal, string $requestId, array $paths): void
    {
        $teleworkerId = $portal->teleworkerId ?? throw new ModelNotFoundException;
        // Locked: two sends at once cannot both pass the check below.
        $request = EvidenceRequest::query()
            ->where('kind', EvidenceKind::Photos)
            ->whereIn('workplace_id', Workplace::query()->where('teleworker_id', $teleworkerId)->whereNull('retired_at')->select('id'))
            ->lockForUpdate()
            ->findOrFail($requestId);

        if ($request->fulfilled_at !== null) {
            throw ValidationException::withMessages(['evidence' => 'Ya envió las fotos de esta solicitud.']);
        }

        $now = now();
        DB::table('evidence_photos')->insert(array_map(fn (string $path): array => [
            'id' => (string) Str::uuid(),
            'tenant_id' => $request->tenant_id,
            'evidence_request_id' => $request->id,
            'path' => $path,
            'uploaded_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $paths));
        $request->update(['fulfilled_at' => $now]);

        Log::info('telework.evidence_fulfilled', ['evidence_request_id' => $request->id, 'workplace_id' => $request->workplace_id, 'kind' => 'photos', 'photo_count' => count($paths)]);
    }
}
