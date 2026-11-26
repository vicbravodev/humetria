<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\SetTenantBrandColor;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

mutates(SetTenantBrandColor::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
});

/** The one «Guardar» of the brand dialog: the accent when asked for, and the logo when one was chosen. */
function pickColor(Tenant $tenant, ?string $color, ?UploadedFile $logo = null): Illuminate\Testing\TestResponse
{
    $fields = $color === null ? [] : ['use_brand_color' => '1', 'brand_color' => $color];

    return test()->actingAs(superadmin())->put(route('companies.brand.update', $tenant), $fields + ($logo === null ? [] : ['logo' => $logo]));
}

it('keeps the accent a company picked, in lowercase', function (): void {
    pickColor($this->tenant, '#E91E63')->assertSessionHasNoErrors()->assertRedirect(route('companies.index'));

    expect($this->tenant->refresh()->brand_color)->toBe('#e91e63');
});

it('goes back to the accent of Humetría when «usar color propio» is off', function (): void {
    pickColor($this->tenant, '#e91e63');

    pickColor($this->tenant, null)->assertSessionHasNoErrors();

    expect($this->tenant->refresh()->brand_color)->toBeNull();
});

it('takes only a six-digit hex color', function (string $color): void {
    pickColor($this->tenant, $color)->assertSessionHasErrors('brand_color');

    expect($this->tenant->refresh()->brand_color)->toBeNull();
})->with(['red', '#fff', '#12345g', 'e91e63', '#e91e63ff', '']);

it('lets only the superadmin pick it, answering anyone else as if the company did not exist', function (): void {
    $this->actingAs(ownerOf($this->tenant))
        ->put(route('companies.brand.update', $this->tenant), ['use_brand_color' => '1', 'brand_color' => '#e91e63'])
        ->assertNotFound();

    expect($this->tenant->refresh()->brand_color)->toBeNull();
});

it('logs the change without the color of anyone but the company', function (): void {
    Log::spy();

    pickColor($this->tenant, '#e91e63');

    Log::shouldHaveReceived('info')->with('tenant.brand_color_updated', ['tenant_id' => $this->tenant->id]);
});

it('gives the other modules the accent of the company', function (): void {
    expect(resolve(Companies::class)->enter($this->tenant->id)->brandColor)->toBeNull();

    pickColor($this->tenant, '#e91e63');

    expect(resolve(Companies::class)->enter($this->tenant->id)->brandColor)->toBe('#e91e63');
});

it('shows the console the accent of each company', function (): void {
    pickColor($this->tenant, '#e91e63');

    $this->actingAs(superadmin())->get(route('companies.index'))
        ->assertInertia(fn ($page) => $page->where('tenants.data.0.brand_color', '#e91e63'));
});

it('saves the logo and the accent in one go', function (): void {
    Storage::fake('public');

    pickColor($this->tenant, '#e91e63', UploadedFile::fake()->image('logo.png', 300, 120))->assertSessionHasNoErrors();

    expect($this->tenant->refresh()->brand_color)->toBe('#e91e63')
        ->and($this->tenant->logo_path)->toStartWith("logos/{$this->tenant->id}/");
});

it('keeps the logo it has when none is chosen', function (): void {
    Storage::fake('public');
    pickColor($this->tenant, '#e91e63', UploadedFile::fake()->image('logo.png', 300, 120));
    $logo = $this->tenant->refresh()->logo_path;

    pickColor($this->tenant, '#1565c0')->assertSessionHasNoErrors();

    expect($this->tenant->refresh()->logo_path)->toBe($logo)->and($this->tenant->brand_color)->toBe('#1565c0');
});

it('saves nothing when the logo is not valid, not even the accent', function (): void {
    Storage::fake('public');

    pickColor($this->tenant, '#e91e63', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))->assertSessionHasErrors('logo');

    expect($this->tenant->refresh()->only(['logo_path', 'brand_color']))->toBe(['logo_path' => null, 'brand_color' => null]);
});
