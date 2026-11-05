<?php

declare(strict_types=1);

namespace App\Modules\Disclosure\Support;

use GdImage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps a piece of evidence on the private disk. The extension and the client's MIME type prove nothing, so the content
 * decides: a PDF is kept as it came; an image must be a real JPEG or PNG that GD can open and is re-encoded, so whatever
 * metadata the camera wrote (GPS included) does not survive. Returns the path.
 */
final readonly class EvidenceStore
{
    /** Pixels GD may be asked to decode: a small file can unpack to gigabytes. */
    private const int MAX_PIXELS = 25_000_000;

    public function __construct(private DisclosureFiles $files) {}

    /**
     * @param  string  $file  Path of the uploaded file, already received by the request.
     * @return string The path of the stored file.
     *
     * @throws ValidationException when it is not a PDF or a JPEG or PNG image, or it is too heavy.
     */
    public function store(string $file, string $directory, string $field = 'evidence'): string
    {
        $bytes = (string) @file_get_contents($file);

        if (mime_content_type($file) === 'application/pdf') {
            return $this->keep($bytes, $directory.'/evidence-'.Str::uuid().'.pdf', config()->integer('disclosure.max_document_kb'), $field);
        }

        $info = @getimagesizefromstring($bytes);
        $type = $info === false ? null : $info[2];

        if ($info === false || ! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw self::invalid($field);
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw self::invalid($field);
        }

        $png = $type === IMAGETYPE_PNG;
        ob_start();
        $png ? imagepng($image) : imagejpeg($image, null, 90);

        return $this->keep((string) ob_get_clean(), $directory.'/evidence-'.Str::uuid().($png ? '.png' : '.jpg'), config()->integer('disclosure.max_photo_kb'), $field);
    }

    private function keep(string $bytes, string $path, int $maxKb, string $field): string
    {
        if (strlen($bytes) > $maxKb * 1024) {
            throw ValidationException::withMessages([$field => 'El archivo debe pesar menos de '.($maxKb / 1024).' MB.']);
        }

        $this->files->put($path, $bytes);

        return $path;
    }

    private static function invalid(string $field): ValidationException
    {
        return ValidationException::withMessages([$field => 'Debe ser un PDF o una imagen JPG o PNG.']);
    }
}
