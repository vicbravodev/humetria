<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\RemoveTenantLogo;
use App\Modules\Tenancy\Actions\SetTenantLogo;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\CompanyBranding;
use App\Modules\Tenancy\Data\CompanyLogo;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

mutates(SetTenantLogo::class, RemoveTenantLogo::class);

beforeEach(function (): void {
    Storage::fake('public');
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
});

/** The one «Guardar» of the brand dialog, with the logo and its version for dark mode. */
function saveBrand(Tenant $tenant, ?UploadedFile $logo, ?UploadedFile $dark): Illuminate\Testing\TestResponse
{
    return test()->actingAs(superadmin())->put(route('companies.brand.update', $tenant), array_filter(['logo' => $logo, 'logo_dark' => $dark]));
}

it('keeps a version of the logo for dark mode beside the one for light', function (): void {
    saveBrand($this->tenant, UploadedFile::fake()->image('a.png', 300, 120), UploadedFile::fake()->image('b.png', 400, 100))->assertSessionHasNoErrors();

    $tenant = $this->tenant->refresh();

    expect($tenant->logo_path)->not->toBeNull()->and($tenant->logo_dark_path)->not->toBeNull()->not->toBe($tenant->logo_path)
        ->and([$tenant->logo_dark_width, $tenant->logo_dark_height])->toBe([160, 40]);
    Storage::disk('public')->assertExists((string) $tenant->logo_dark_path);
});

it('adds the dark version to a company that already has its logo, keeping it', function (): void {
    saveBrand($this->tenant, UploadedFile::fake()->image('a.png', 300, 120), null);
    $light = $this->tenant->refresh()->logo_path;

    saveBrand($this->tenant, null, UploadedFile::fake()->image('b.png', 300, 120))->assertSessionHasNoErrors();

    expect($this->tenant->refresh()->logo_path)->toBe($light)->and($this->tenant->logo_dark_path)->not->toBeNull();
});

it('replaces the dark version, removing the file of the one before', function (): void {
    saveBrand($this->tenant, UploadedFile::fake()->image('a.png', 300, 120), UploadedFile::fake()->image('b.png', 300, 120));
    $before = (string) $this->tenant->refresh()->logo_dark_path;

    saveBrand($this->tenant, null, UploadedFile::fake()->image('c.png', 300, 120));

    expect($this->tenant->refresh()->logo_dark_path)->not->toBe($before);
    Storage::disk('public')->assertMissing($before)->assertExists((string) $this->tenant->logo_dark_path);
});

it('refuses a dark version while the company has no logo for light: it would show nowhere', function (): void {
    saveBrand($this->tenant, null, UploadedFile::fake()->image('b.png', 300, 120))->assertSessionHasErrors('logo_dark');

    expect($this->tenant->refresh()->logo_dark_path)->toBeNull();
});

it('asks of the dark version what it asks of the logo', function (Closure $file): void {
    saveBrand($this->tenant, UploadedFile::fake()->image('a.png', 300, 120), $file())->assertSessionHasErrors('logo_dark');

    expect($this->tenant->refresh()->only(['logo_path', 'logo_dark_path']))->toBe(['logo_path' => null, 'logo_dark_path' => null]);
})->with([
    'an SVG' => [fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')],
    'over 2 MB' => [fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 400, 100)->size(2049)],
    'under 40 px tall' => [fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 200, 39)],
]);

it('takes off only the dark version, and its file', function (): void {
    saveBrand($this->tenant, UploadedFile::fake()->image('a.png', 300, 120), UploadedFile::fake()->image('b.png', 300, 120));
    $tenant = $this->tenant->refresh();
    [$light, $dark] = [(string) $tenant->logo_path, (string) $tenant->logo_dark_path];

    $this->actingAs(superadmin())->delete(route('companies.logo-dark.destroy', $this->tenant))->assertRedirect(route('companies.index'));

    expect($this->tenant->refresh()->only(['logo_path', 'logo_dark_path', 'logo_dark_width', 'logo_dark_height']))
        ->toBe(['logo_path' => $light, 'logo_dark_path' => null, 'logo_dark_width' => null, 'logo_dark_height' => null]);
    Storage::disk('public')->assertMissing($dark)->assertExists($light);
});

it('takes the dark version off with the logo', function (): void {
    saveBrand($this->tenant, UploadedFile::fake()->image('a.png', 300, 120), UploadedFile::fake()->image('b.png', 300, 120));
    $dark = (string) $this->tenant->refresh()->logo_dark_path;

    $this->actingAs(superadmin())->delete(route('companies.logo.destroy', $this->tenant));

    expect($this->tenant->refresh()->only(['logo_path', 'logo_dark_path']))->toBe(['logo_path' => null, 'logo_dark_path' => null]);
    Storage::disk('public')->assertMissing($dark);
});

it('lets only the superadmin take it off, answering anyone else as if the company did not exist', function (): void {
    $this->actingAs(ownerOf($this->tenant))->delete(route('companies.logo-dark.destroy', $this->tenant))->assertNotFound();
});

it('gives the other modules and the pages of the company its dark logo, with the size to show it at', function (): void {
    saveBrand($this->tenant, UploadedFile::fake()->image('a.png', 300, 120), UploadedFile::fake()->image('b.png', 400, 100));
    $url = Storage::disk('public')->url((string) $this->tenant->refresh()->logo_dark_path);

    expect(resolve(Companies::class)->enter($this->tenant->id)->darkLogo)->toEqual(new CompanyLogo($url, 160, 40))
        ->and(resolve(CompanyBranding::class)->current()?->darkLogo)->toEqual(new CompanyLogo($url, 160, 40));
});

it('has no dark logo while none was given', function (): void {
    expect(resolve(Companies::class)->enter($this->tenant->id)->darkLogo)->toBeNull();
});

it('shows the console the dark logo of each company', function (): void {
    saveBrand($this->tenant, UploadedFile::fake()->image('a.png', 300, 120), UploadedFile::fake()->image('b.png', 300, 120));

    $this->actingAs(superadmin())->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page->where('tenants.data.0.logo_dark_url', Storage::disk('public')->url((string) $this->tenant->refresh()->logo_dark_path)));
});
