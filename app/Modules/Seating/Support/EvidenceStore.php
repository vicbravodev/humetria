<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use Illuminate\Support\Str;

/**
 * Keeps a piece of evidence on the private disk: a PDF as it came (the request already read its content), an image
 * re-encoded by SeatPhotoStore so no camera metadata survives. Returns the path.
 */
final readonly class EvidenceStore
{
    public function __construct(private SeatingFiles $files, private SeatPhotoStore $photos) {}

    public function store(string $file, string $directory): string
    {
        if (mime_content_type($file) !== 'application/pdf') {
            return $this->photos->store($file, $directory, 'evidence');
        }

        $path = $directory.'/evidence-'.Str::uuid().'.pdf';
        $this->files->put($path, (string) file_get_contents($file));

        return $path;
    }
}
