<?php

declare(strict_types=1);

use App\Modules\Tenancy\Actions\SetTenantBrandColor;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;

mutates(SetTenantBrandColor::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme']);
});

function pickColor(Tenant $tenant, ?string $color): Illuminate\Testing\TestResponse
{
    return test()->actingAs(superadmin())->put(route('companies.brand-color.update', $tenant), ['brand_color' => $color]);
}

it('keeps the accent a company picked, in lowercase', function (): void {
    pickColor($this->tenant, '#E91E63')->assertSessionHasNoErrors()->assertRedirect(route('companies.index'));

    expect($this->tenant->refresh()->brand_color)->toBe('#e91e63');
});

it('goes back to the accent of Humetría when the color is cleared', function (): void {
    pickColor($this->tenant, '#e91e63');

    pickColor($this->tenant, null)->assertSessionHasNoErrors();

    expect($this->tenant->refresh()->brand_color)->toBeNull();
});

it('takes only a six-digit hex color', function (string $color): void {
    pickColor($this->tenant, $color)->assertSessionHasErrors('brand_color');

    expect($this->tenant->refresh()->brand_color)->toBeNull();
})->with(['red', '#fff', '#12345g', 'e91e63', '#e91e63ff']);

it('lets only the superadmin pick it, answering anyone else as if the company did not exist', function (): void {
    $this->actingAs(ownerOf($this->tenant))
        ->put(route('companies.brand-color.update', $this->tenant), ['brand_color' => '#e91e63'])
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
