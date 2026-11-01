<?php

declare(strict_types=1);

use App\Modules\Telework\Support\FileStore;
use App\Modules\Telework\Support\PhotoStore;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

mutates(PhotoStore::class, FileStore::class);

beforeEach(function (): void {
    Storage::fake('telework_files');
    config()->set('filesystems.telework_files', 'telework_files');
});

function fixtureUpload(string $file, ?string $as = null): UploadedFile
{
    return new UploadedFile(__DIR__.'/../../Fixtures/'.$file, $as ?? $file, null, null, true);
}

it('stores a jpeg without its metadata, GPS included', function (): void {
    expect(exif_read_data(__DIR__.'/../../Fixtures/photo-with-gps.jpg'))->toHaveKey('GPSLatitude');

    $path = resolve(PhotoStore::class)->store(fixtureUpload('photo-with-gps.jpg'), 'photos/a');

    expect($path)->toStartWith('photos/a/')->toEndWith('.jpg');
    Storage::disk('telework_files')->assertExists($path);

    $stored = Storage::disk('telework_files')->path($path);
    expect(@exif_read_data($stored))->not->toHaveKey('GPSLatitude')
        ->and(getimagesize($stored)[2])->toBe(IMAGETYPE_JPEG);
});

it('stores a png as a png', function (): void {
    $path = resolve(PhotoStore::class)->store(fixtureUpload('photo.png'), 'photos/a');

    expect($path)->toEndWith('.png')
        ->and(getimagesize(Storage::disk('telework_files')->path($path))[2])->toBe(IMAGETYPE_PNG);
});

it('refuses what GD cannot open even if it is named .jpg', function (): void {
    $pdf = UploadedFile::fake()->createWithContent('photo.jpg', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n");

    expect(fn () => resolve(PhotoStore::class)->store($pdf, 'photos/a'))->toThrow(ValidationException::class);
    expect(Storage::disk('telework_files')->allFiles())->toBe([]);
});

it('refuses a gif even though GD opens it', function (): void {
    $gif = UploadedFile::fake()->createWithContent('photo.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', true) ?: '');

    expect(fn () => resolve(PhotoStore::class)->store($gif, 'photos/a'))->toThrow(ValidationException::class);
});

it('accepts a photo of exactly the limit and refuses one byte more', function (): void {
    config()->set('telework.max_photo_kb', 1);
    $jpeg = (string) file_get_contents(__DIR__.'/../../Fixtures/photo.jpg');
    // A comment segment pads a valid jpeg to the size wanted.
    $of = function (int $size) use ($jpeg): UploadedFile {
        $padding = $size - strlen($jpeg) - 4;

        return UploadedFile::fake()->createWithContent('photo.jpg', substr($jpeg, 0, 2)."\xFF\xFE".pack('n', $padding + 2).str_repeat('a', $padding).substr($jpeg, 2));
    };

    expect(resolve(PhotoStore::class)->store($of(1024), 'photos/a'))->toEndWith('.jpg');
    expect(fn () => resolve(PhotoStore::class)->store($of(1025), 'photos/a'))->toThrow(ValidationException::class);
});

it('puts, streams and deletes private files', function (): void {
    $files = resolve(FileStore::class);

    $files->put('x/y.txt', 'hola');
    Storage::disk('telework_files')->assertExists('x/y.txt');
    ob_start();
    $files->stream('x/y.txt')->sendContent();
    expect(ob_get_clean())->toBe('hola');

    $files->delete('x/y.txt', 'x/missing.txt');
    Storage::disk('telework_files')->assertMissing('x/y.txt');
});

/**
 * A jpeg of 20x10 pixels, black with its top-left quarter white, whose EXIF says how it must be turned to be seen upright
 * (Orientation 6, a portrait phone photo, by default); without EXIF when the orientation is null.
 */
function orientedJpeg(?int $orientation = 6): UploadedFile
{
    $image = imagecreatetruecolor(20, 10);
    imagefilledrectangle($image, 0, 0, 9, 4, (int) imagecolorallocate($image, 255, 255, 255));
    ob_start();
    imagejpeg($image, null, 100);
    $jpeg = (string) ob_get_clean();

    if ($orientation === null) {
        return UploadedFile::fake()->createWithContent('photo.jpg', $jpeg);
    }

    $tiff = "II*\0".pack('V', 8).pack('v', 1).pack('vvVv', 0x0112, 3, 1, $orientation).pack('v', 0).pack('V', 0);
    $segment = "Exif\0\0".$tiff;

    return UploadedFile::fake()->createWithContent('portrait.jpg', substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($segment) + 2).$segment.substr($jpeg, 2));
}

/** Which quarter of a stored photo is the white one: tl, tr, bl or br. */
function whiteQuarter(string $path): string
{
    $image = imagecreatefromstring((string) file_get_contents($path));
    expect($image)->toBeInstanceOf(GdImage::class);
    $width = imagesx($image);
    $height = imagesy($image);
    $white = [];

    foreach (['tl' => [0.25, 0.25], 'tr' => [0.75, 0.25], 'bl' => [0.25, 0.75], 'br' => [0.75, 0.75]] as $quarter => [$x, $y]) {
        $rgb = imagecolorat($image, (int) ($width * $x), (int) ($height * $y));

        if ((($rgb >> 16) & 0xFF) > 128) {
            $white[] = $quarter;
        }
    }

    return implode(',', $white);
}

it('turns a photo upright by its EXIF orientation before dropping the metadata', function (?int $orientation, bool $swapped, string $quarter): void {
    $upload = orientedJpeg($orientation);
    expect(@exif_read_data($upload->getPathname())['Orientation'] ?? null)->toBe($orientation);

    $path = resolve(PhotoStore::class)->store($upload, 'photos/a');

    $stored = Storage::disk('telework_files')->path($path);
    expect(getimagesize($stored)[0])->toBe($swapped ? 10 : 20)->and(getimagesize($stored)[1])->toBe($swapped ? 20 : 10)
        ->and(@exif_read_data($stored))->not->toHaveKey('Orientation')
        ->and(whiteQuarter($stored))->toBe($quarter);
})->with([
    'no EXIF' => [null, false, 'tl'],
    'upright' => [1, false, 'tl'],
    'mirrored' => [2, false, 'tr'],
    'upside down' => [3, false, 'br'],
    'mirrored upside down' => [4, false, 'bl'],
    'transposed' => [5, true, 'tl'],
    'turned left' => [6, true, 'tr'],
    'transversed' => [7, true, 'br'],
    'turned right' => [8, true, 'bl'],
]);

it('reads the orientation from the uploaded file on disk, so it works with allow_url_fopen off (no data:// stream)', function (): void {
    $upload = orientedJpeg(6);
    // Without the data:// wrapper, as a server that cannot open URLs would behave.
    stream_wrapper_unregister('data');

    try {
        $path = resolve(PhotoStore::class)->store($upload, 'photos/a');
    } finally {
        stream_wrapper_restore('data');
    }

    expect(getimagesize(Storage::disk('telework_files')->path($path))[0])->toBe(10);
});

it('says how much a photo may weigh', function (): void {
    config()->set('telework.max_photo_kb', 2048);
    $heavy = UploadedFile::fake()->createWithContent('photo.jpg', str_repeat('a', 2048 * 1024 + 1));

    expect(fn () => resolve(PhotoStore::class)->store($heavy, 'photos/a'))->toThrow(ValidationException::class, 'Cada foto debe pesar menos de 2 MB.');
});

it('refuses a png whose header is fine but whose pixels GD cannot decode', function (): void {
    $png = (string) file_get_contents(__DIR__.'/../../Fixtures/photo.png');
    $data = strpos($png, 'IDAT') + 4;
    $broken = substr($png, 0, $data).str_repeat("\0", strlen($png) - $data);

    expect(fn () => resolve(PhotoStore::class)->store(UploadedFile::fake()->createWithContent('broken.png', $broken), 'photos/a'))
        ->toThrow(ValidationException::class, 'Cada foto debe ser una imagen JPG o PNG.');
    expect(Storage::disk('telework_files')->allFiles())->toBe([]);
});

it('refuses an upload that did not arrive whole', function (): void {
    $partial = new UploadedFile(__DIR__.'/../../Fixtures/photo.jpg', 'photo.jpg', null, UPLOAD_ERR_PARTIAL, true);

    expect(fn () => resolve(PhotoStore::class)->store($partial, 'photos/a'))->toThrow(ValidationException::class);
});

it('refuses an image with more than 25 million pixels', function (): void {
    $png = (string) file_get_contents(__DIR__.'/../../Fixtures/photo.png');
    // Only the header says how big it is: 5001 x 5000 = 25,005,000 pixels.
    $huge = substr($png, 0, 16).pack('NN', 5001, 5000).substr($png, 24);

    expect(fn () => resolve(PhotoStore::class)->store(UploadedFile::fake()->createWithContent('big.png', $huge), 'photos/a'))->toThrow(ValidationException::class);
});

it('deletes the photos already stored when a later one fails for any reason', function (): void {
    $broken = new class(__DIR__.'/../../Fixtures/photo.jpg', 'x.jpg', null, null, true) extends UploadedFile
    {
        public function getContent(): string
        {
            throw new RuntimeException('disk gone');
        }
    };

    expect(fn () => resolve(PhotoStore::class)->storeMany(['general' => [fixtureUpload('photo.png'), $broken]], 'photos/a'))->toThrow(RuntimeException::class);
    expect(Storage::disk('telework_files')->allFiles())->toBe([]);
});
