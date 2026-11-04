<?php

declare(strict_types=1);

namespace App\Modules\Interventions\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The private disk of the Programa de intervención evidence (attendance lists, call-ups, photos). Nothing here is ever a
 * public URL: files leave only through `stream()`, after the caller checked who may see them. It is a copy of the Ley Silla
 * one on purpose (extracting it to the shared kernel touches two delivered modules; it is noted as debt).
 */
final readonly class InterventionFiles
{
    public function put(string $path, string $contents): void
    {
        if (! $this->disk()->put($path, $contents)) {
            throw new RuntimeException('The file could not be stored.');
        }
    }

    public function stream(string $path): StreamedResponse
    {
        return $this->disk()->response($path);
    }

    /** Missing paths are fine: deleting twice, or after a rollback, must not fail. */
    public function delete(string ...$paths): void
    {
        $this->disk()->delete($paths);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config()->string('filesystems.intervention_files'));
    }
}
