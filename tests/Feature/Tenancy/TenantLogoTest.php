<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\RemoveTenantLogo;
use App\Modules\Tenancy\Actions\SetTenantLogo;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Data\CompanyLogo;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

mutates(SetTenantLogo::class, RemoveTenantLogo::class);

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
