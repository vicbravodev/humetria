<?php

declare(strict_types=1);

namespace App\Modules\Seating\Support;

use GdImage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps the photo of a seat on the private disk. Only real JPEG or PNG images that GD can open are accepted (the extension
 * and the client's MIME type prove nothing), and they are re-encoded: whatever metadata the camera wrote, GPS included,
 * does not survive.
 */
final readonly class SeatPhotoStore
{
    /** Pixels GD may be asked to decode: a small file can unpack to gigabytes. */
    private const int MAX_PIXELS = 25_000_000;

    public function __construct(private SeatingFiles $files) {}

    /**
     * @param  string  $file  Path of the uploaded file, already received by the request.
     * @return string The path of the stored photo.
     *
     * @throws ValidationException when the file is not a JPEG or PNG image, or is too heavy.
     */
    public function store(string $file, string $directory): string
    {
        $bytes = (string) @file_get_contents($file);

        if (strlen($bytes) > config()->integer('seating.max_photo_kb') * 1024) {
            throw ValidationException::withMessages(['photo' => 'La foto debe pesar menos de '.(config()->integer('seating.max_photo_kb') / 1024).' MB.']);
        }

        $info = getimagesizefromstring($bytes);
        $type = $info === false ? null : $info[2];

        if ($info === false || ! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $info[0] * $info[1] > self::MAX_PIXELS) {
            throw $this->invalid();
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw $this->invalid();
        }

        $png = $type === IMAGETYPE_PNG;
        ob_start();
        $png ? imagepng($image) : imagejpeg($image, null, 90);
        $path = $directory.'/seat-'.Str::uuid().($png ? '.png' : '.jpg');
        $this->files->put($path, (string) ob_get_clean());

        return $path;
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['photo' => 'La foto debe ser una imagen JPG o PNG.']);
    }
}
