<?php

declare(strict_types=1);

namespace App\Modules\Telework\Actions;

use App\Modules\Telework\Models\VisitAuthorization;
use App\Modules\Telework\Support\FileStore;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * RH or the Comisión attaches the person's signed paper authorization (a PDF) instead of the portal confirmation. A
 * document already attached is evidence and stays.
 */
final readonly class AttachVisitDocument
{
    public function __construct(private FileStore $files) {}

    /**
     * @param  string  $file  Path of the uploaded PDF, already validated.
     *
     * @throws ValidationException when the authorization already has its document or was cancelled.
     */
    public function handle(VisitAuthorization $authorization, string $file): void
    {
        if ($authorization->cancelled_at !== null) {
            throw ValidationException::withMessages(['document' => 'Esta solicitud de visita fue cancelada.']);
        }

        if ($authorization->document_path !== null) {
            throw ValidationException::withMessages(['document' => 'Esta autorización ya tiene su documento.']);
        }

        $contents = file_get_contents($file);

        // Laravel turns the warning of a failed read into an ErrorException first: the check only narrows the type.
        if ($contents === false) { // @pest-mutate-ignore: FalseToTrue
            throw new RuntimeException('The signed authorization could not be read.');
        }

        $path = "visits/{$authorization->tenant_id}/{$authorization->workplace_id}/authorization-".Str::uuid().'.pdf';
        $this->files->put($path, $contents);

        try {
            $authorization->update(['document_path' => $path]);
        } catch (Throwable $failure) {
            $this->files->delete($path);

            throw $failure;
        }

        Log::info('telework.visit_document_attached', ['visit_authorization_id' => $authorization->id, 'workplace_id' => $authorization->workplace_id]);
    }
}
