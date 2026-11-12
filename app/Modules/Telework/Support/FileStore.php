<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use App\Modules\Billing\Contracts\StorageGuard;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The private disk of the NOM-037 evidence (photos, PDFs). Nothing here is ever a public URL: files leave only through
 * `stream()`, after the caller checked who may see them.
 */
final readonly class FileStore
{
    public function __construct(private StorageGuard $storage) {}

    /** @throws RuntimeException when the plan's storage cannot hold a new file this size. */
    public function put(string $path, string $contents): void
    {
        $this->storage->admit($path, strlen($contents));

        if (! $this->disk()->put($path, $contents)) {
            $this->storage->release($path, strlen($contents));

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
        return Storage::disk(config()->string('filesystems.telework_files'));
    }
}
