<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Keeps a photo of a workplace on the private disk. Only real JPEG or PNG images that GD can open are accepted (the
 * extension and the client's MIME type prove nothing), and they are re-encoded: whatever metadata the camera wrote, GPS
 * included, does not survive.
 */
final readonly class PhotoStore
{
    /** Pixels GD may be asked to decode: a small file can unpack to gigabytes. */
    private const MAX_PIXELS = 50_000_000;

    public function __construct(private FileStore $files) {}

    /**
     * @return string The path of the stored photo.
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file, string $directory): string
    {
        $bytes = $file->getContent();

        if (strlen($bytes) > config()->integer('telework.max_photo_kb') * 1024) {
            throw ValidationException::withMessages(['photos' => 'Cada foto debe pesar menos de '.(config()->integer('telework.max_photo_kb') / 1024).' MB.']);
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

        $path = $directory.'/photo-'.Str::uuid().($type === IMAGETYPE_PNG ? '.png' : '.jpg');
        $this->files->put($path, $this->encode($image, $type === IMAGETYPE_PNG));

        return $path;
    }

    private function encode(GdImage $image, bool $png): string
    {
        ob_start();
        $png ? imagepng($image) : imagejpeg($image, null, 90);

        return (string) ob_get_clean();
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['photos' => 'Cada foto debe ser una imagen JPG o PNG.']);
    }
}
