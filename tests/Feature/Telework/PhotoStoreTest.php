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
