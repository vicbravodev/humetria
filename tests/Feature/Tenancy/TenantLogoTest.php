<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\RemoveTenantLogo;
use App\Modules\Tenancy\Actions\SetTenantLogo;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\CompanyLogo;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\LogoBounds;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

mutates(SetTenantLogo::class, RemoveTenantLogo::class, LogoBounds::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
});

function uploadLogo(Tenant $tenant, UploadedFile|string $file): Illuminate\Testing\TestResponse
{
    return test()->actingAs(superadmin())->post(route('companies.logo.update', $tenant), ['logo' => $file]);
}

it('keeps the logo as a PNG 80 px tall, shown at 40 px in the emails', function (): void {
    uploadLogo($this->tenant, UploadedFile::fake()->image('logo.jpg', 300, 120))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('companies.index'));

    $tenant = $this->tenant->refresh();
    [$width, $height] = getimagesizefromstring((string) Storage::disk('public')->get((string) $tenant->logo_path)) ?: [0, 0];

    expect($tenant->logo_path)->toStartWith("logos/{$tenant->id}/")->toEndWith('.png')
        ->and([$width, $height])->toBe([200, 80])
        ->and([$tenant->logo_width, $tenant->logo_height])->toBe([100, 40]);
});

it('fits a wide logo in 200 px', function (): void {
    uploadLogo($this->tenant, UploadedFile::fake()->image('logo.png', 1000, 100))->assertSessionHasNoErrors();

    expect([$this->tenant->refresh()->logo_width, $this->tenant->logo_height])->toBe([200, 20]);
});

it('keeps the transparency of a PNG', function (): void {
    $image = imagecreatetruecolor(100, 40);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
    imagepng($image, $path);

    uploadLogo($this->tenant, new UploadedFile($path, 'logo.png', 'image/png', test: true))->assertSessionHasNoErrors();

    $stored = imagecreatefromstring((string) Storage::disk('public')->get((string) $this->tenant->refresh()->logo_path));
    expect($stored)->not->toBeFalse()
        ->and(imagecolorat($stored, 0, 0) >> 24)->toBe(127);
});

it('replaces the logo, removing the file of the one before', function (): void {
    uploadLogo($this->tenant, UploadedFile::fake()->image('a.png', 200, 80));
    $before = (string) $this->tenant->refresh()->logo_path;

    uploadLogo($this->tenant, UploadedFile::fake()->image('b.png', 200, 80));

    expect($this->tenant->refresh()->logo_path)->not->toBe($before);
    Storage::disk('public')->assertMissing($before)->assertExists((string) $this->tenant->logo_path);
});

it('removes the logo and its file', function (): void {
    uploadLogo($this->tenant, UploadedFile::fake()->image('a.png', 200, 80));
    $path = (string) $this->tenant->refresh()->logo_path;

    $this->actingAs(superadmin())->delete(route('companies.logo.destroy', $this->tenant))->assertRedirect(route('companies.index'));

    expect($this->tenant->refresh()->only(['logo_path', 'logo_width', 'logo_height']))->toBe(['logo_path' => null, 'logo_width' => null, 'logo_height' => null]);
    Storage::disk('public')->assertMissing($path);
});

it('logs who changed the logo, never the file', function (): void {
    Log::spy();

    uploadLogo($this->tenant, UploadedFile::fake()->image('a.png', 200, 80));
    $this->actingAs(superadmin())->delete(route('companies.logo.destroy', $this->tenant));

    Log::shouldHaveReceived('info')->with('tenant.logo_updated', ['tenant_id' => $this->tenant->id]);
    Log::shouldHaveReceived('info')->with('tenant.logo_removed', ['tenant_id' => $this->tenant->id]);
});

it('takes only a PNG, JPG or WebP image of up to 2 MB and at least 40 px tall', function (Closure $file): void {
    uploadLogo($this->tenant, $file())->assertSessionHasErrors('logo');

    expect($this->tenant->refresh()->logo_path)->toBeNull();
})->with([
    'an SVG' => [fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')],
    'a PDF' => [fn (): UploadedFile => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')],
    'over 2 MB' => [fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 400, 100)->size(2049)],
    'under 40 px tall' => [fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 200, 39)],
    // A small file can decode into a huge bitmap: past 4000 px a side it is refused before anything decodes it.
    'over 4000 px wide' => [fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 4001, 40)],
    'over 4000 px tall' => [fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 40, 4001)],
    'nothing' => [fn (): string => ''],
]);

it('lets only the superadmin change a logo, answering anyone else as if it did not exist', function (): void {
    $owner = ownerOf($this->tenant);

    $this->actingAs($owner)
        ->post(route('companies.logo.update', $this->tenant), ['logo' => UploadedFile::fake()->image('a.png', 200, 80)])
        ->assertNotFound();
    $this->actingAs($owner)->delete(route('companies.logo.destroy', $this->tenant))->assertNotFound();

    expect($this->tenant->refresh()->logo_path)->toBeNull();
});

it('gives the other modules the logo of the company, with the size to show it at', function (): void {
    expect(resolve(Companies::class)->enter($this->tenant->id)->logo)->toBeNull();

    uploadLogo($this->tenant, UploadedFile::fake()->image('a.png', 300, 120));

    expect(resolve(Companies::class)->enter($this->tenant->id)->logo)
        ->toEqual(new CompanyLogo(Storage::disk('public')->url((string) $this->tenant->refresh()->logo_path), 100, 40));
});

it('shows the console the logo of each company', function (): void {
    uploadLogo($this->tenant, UploadedFile::fake()->image('a.png', 300, 120));

    $this->actingAs(superadmin())->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page->where('tenants.data.0.logo_url', Storage::disk('public')->url((string) $this->tenant->refresh()->logo_path)));
});

/** A 400 x 200 image with a 100 x 40 mark in the middle: on a transparent background, or on an opaque one of the given color. */
function logoWithMargins(?int $background): UploadedFile
{
    $image = imagecreatetruecolor(400, 200);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, $background ?? (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 150, 80, 249, 119, (int) imagecolorallocate($image, 20, 60, 160));
    $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
    imagepng($image, $path);

    return new UploadedFile($path, 'logo.png', 'image/png', test: true);
}

/** @return array{int, int} the stored logo's width and height in px */
function storedLogoSize(Tenant $tenant): array
{
    [$width, $height] = getimagesizefromstring((string) Storage::disk('public')->get((string) $tenant->refresh()->logo_path)) ?: [0, 0];

    return [$width, $height];
}

it('trims the empty margins around the mark, so the logo fills the space it is given', function (?int $background): void {
    uploadLogo($this->tenant, logoWithMargins($background))->assertSessionHasNoErrors();

    [$width, $height] = storedLogoSize($this->tenant);

    // The mark is 100 x 40 (2.5 : 1): with the margins gone the logo has that shape, give or take its breathing room.
    expect($height)->toBe(80)
        ->and($width / $height)->toBeGreaterThan(2.2)->toBeLessThan(2.8);
})->with([
    'transparent' => [null],
    'white' => [0xFFFFFF],
]);

it('does not trim a logo that already reaches its edges', function (): void {
    $image = imagecreatetruecolor(300, 120);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 255, 255, 255));
    imagefilledrectangle($image, 0, 0, 299, 119, (int) imagecolorallocate($image, 20, 60, 160));
    $path = tempnam(sys_get_temp_dir(), 'logo').'.png';
    imagepng($image, $path);

    uploadLogo($this->tenant, new UploadedFile($path, 'logo.png', 'image/png', test: true));

    expect(storedLogoSize($this->tenant))->toBe([200, 80]);
});

/** An image of the given size with a filled box: background and mark as [red, green, blue, alpha (0 opaque, 127 clear)]. */
function logoImage(int $width, int $height, array $background, array $mark, array $box): GdImage
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, ...$background));
    imagefilledrectangle($image, ...[...$box, (int) imagecolorallocatealpha($image, ...$mark)]);

    return $image;
}

it('finds the mark with a breathing room of 3 % of its longest side', function (): void {
    // The mark spans x 150–249 and y 80–119: 99 px wide, so 3 px of room on every side.
    expect(LogoBounds::of(logoImage(400, 200, [255, 255, 255, 0], [20, 60, 160, 0], [150, 80, 249, 119])))
        ->toBe(['x' => 147, 'y' => 77, 'width' => 106, 'height' => 46]);
});

it('searches a logo wider than 400 px on a copy 400 px wide, and gives the box in the logo\'s own pixels', function (): void {
    expect(LogoBounds::of(logoImage(800, 400, [255, 255, 255, 0], [20, 60, 160, 0], [300, 160, 499, 239])))
        ->toBe(['x' => 294, 'y' => 154, 'width' => 212, 'height' => 92]);
});

it('takes a color up to 32 away from a plain background as background, and 33 away as the mark', function (array $mark, array $box): void {
    expect(LogoBounds::of(logoImage(400, 200, [255, 255, 255, 0], $mark, [150, 80, 249, 119])))->toBe($box);
})->with([
    '32 away' => [[223, 223, 223, 0], ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 200]],
    '33 away in red' => [[222, 255, 255, 0], ['x' => 147, 'y' => 77, 'width' => 106, 'height' => 46]],
    '33 away in green' => [[255, 222, 255, 0], ['x' => 147, 'y' => 77, 'width' => 106, 'height' => 46]],
    '33 away in blue' => [[255, 255, 222, 0], ['x' => 147, 'y' => 77, 'width' => 106, 'height' => 46]],
]);

it('takes a pixel of alpha 110 or more as empty, and 109 as the mark', function (int $alpha, array $box): void {
    expect(LogoBounds::of(logoImage(400, 200, [0, 0, 0, 127], [20, 60, 160, $alpha], [150, 80, 249, 119])))->toBe($box);
})->with([
    'alpha 110' => [110, ['x' => 0, 'y' => 0, 'width' => 400, 'height' => 200]],
    'alpha 109' => [109, ['x' => 147, 'y' => 77, 'width' => 106, 'height' => 46]],
]);
