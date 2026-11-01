<?php

declare(strict_types=1);

namespace App\Modules\Telework\Support;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Keeps a photo of a workplace on the private disk. Only real JPEG or PNG images that GD can open are accepted (the
 * extension and the client's MIME type prove nothing), and they are re-encoded: whatever metadata the camera wrote, GPS
 * included, does not survive.
 */
final readonly class PhotoStore
{
    /** Pixels GD may be asked to decode: a small file can unpack to gigabytes. */
    private const MAX_PIXELS = 25_000_000;

    public function __construct(private FileStore $files) {}

    /**
     * @return string The path of the stored photo.
     *
     * @throws ValidationException
     */
    public function store(UploadedFile $file, string $directory): string
    {
        if (! $file->isValid()) {
            throw $this->invalid();
        }

        $bytes = $file->getContent();

        if (strlen($bytes) > config()->integer('telework.max_photo_kb') * 1024) {
            throw ValidationException::withMessages(['photos' => 'Cada foto debe pesar menos de '.(config()->integer('telework.max_photo_kb') / 1024).' MB.']);
        }

        $info = getimagesizefromstring($bytes);
        $type = $info === false ? null : $info[2];

        // Without $info the type is null and in_array() refuses it already: `=== true` there changes nothing.
        if ($info === false || ! in_array($type, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $info[0] * $info[1] > self::MAX_PIXELS) { // @pest-mutate-ignore: FalseToTrue
            throw $this->invalid();
        }

        $image = @imagecreatefromstring($bytes);

        if (! $image instanceof GdImage) {
            throw $this->invalid();
        }

        if ($type === IMAGETYPE_JPEG) {
            $image = $this->upright($image, $file->getPathname());
        }

        $path = $directory.'/photo-'.Str::uuid().($type === IMAGETYPE_PNG ? '.png' : '.jpg');
        $this->files->put($path, $this->encode($image, $type === IMAGETYPE_PNG));

        return $path;
    }

    /**
     * Stores every photo or none: if one is refused, those already kept are deleted.
     *
     * @param  array<string, list<UploadedFile>>  $files  By question id, or `general`.
     * @return array<string, list<string>> The stored paths, by the same keys.
     *
     * @throws ValidationException
     */
    public function storeMany(array $files, string $directory): array
    {
        $paths = [];

        try {
            foreach ($files as $key => $group) {
                foreach ($group as $file) {
                    $paths[$key][] = $this->store($file, $directory);
                }
            }
        } catch (Throwable $failure) {
            $this->files->delete(...array_merge(...array_values($paths)));

            throw $failure;
        }

        return $paths;
    }

    /**
     * Re-encoding drops the EXIF orientation, so the pixels are turned to match it first. EXIF is read from the upload's own
     * file on disk (PHP manages it), never a `data://` stream, so it works on a server with `allow_url_fopen` off.
     */
    private function upright(GdImage $image, string $path): GdImage
    {
        $exif = @exif_read_data($path);

        // Without EXIF the photo stays as it is: 1 (upright) and 0 (unknown) both fall to the default arm.
        $orientation = is_array($exif) && is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1; // @pest-mutate-ignore: DecrementInteger

        $turned = match ($orientation) {
            2 => $this->flip($image, IMG_FLIP_HORIZONTAL),
            3 => $this->rotate($image, 180),
            4 => $this->flip($image, IMG_FLIP_VERTICAL),
            5 => $this->flip($this->rotate($image, -90), IMG_FLIP_HORIZONTAL),
            6 => $this->rotate($image, -90),
            7 => $this->flip($this->rotate($image, 90), IMG_FLIP_HORIZONTAL),
            8 => $this->rotate($image, 90),
            default => $image,
        };

        // The caller replaces its reference with the result, so the original is freed before encoding. GD fails to turn only
        // when it runs out of memory, which no test can bring about: then the photo is kept as it came.
        return $turned instanceof GdImage ? $turned : $image; // @pest-mutate-ignore: InstanceOfToTrue
    }

    /** A turn by a right angle: no corner is left uncovered, so the background colour never shows. */
    private function rotate(GdImage $image, int $degrees): GdImage|false
    {
        return imagerotate($image, $degrees, 0); // @pest-mutate-ignore: IncrementInteger,DecrementInteger
    }

    private function flip(GdImage|false $image, int $mode): GdImage|false
    {
        // $image is false only when a turn failed (see upright()): `!== true` holds for every image GD gives back.
        if ($image !== false) { // @pest-mutate-ignore: FalseToTrue
            imageflip($image, $mode);
        }

        return $image;
    }

    private function encode(GdImage $image, bool $png): string
    {
        ob_start();
        $png ? imagepng($image) : imagejpeg($image, null, 90);

        // The buffer was opened just above, so ob_get_clean() returns a string: the cast only narrows the type.
        return (string) ob_get_clean(); // @pest-mutate-ignore: RemoveStringCast
    }

    private function invalid(): ValidationException
    {
        return ValidationException::withMessages(['photos' => 'Cada foto debe ser una imagen JPG o PNG.']);
    }
}
