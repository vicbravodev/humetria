<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps a PDF on the private disk. The extension and the client's MIME type prove nothing, so the content decides.
 */
final readonly class PdfStore
{
    public function __construct(private SeatingFiles $files) {}

    /**
     * @return string The path of the stored file.
     *
     * @throws ValidationException when the file is not a PDF.
     */
    public function store(string $file, string $directory, string $field = 'file'): string
    {
        if (mime_content_type($file) !== 'application/pdf') {
            throw ValidationException::withMessages([$field => 'Debe ser un archivo PDF.']);
        }

        $path = $directory.'/document-'.Str::uuid().'.pdf';
        $this->files->put($path, (string) file_get_contents($file));

        return $path;
    }
}
