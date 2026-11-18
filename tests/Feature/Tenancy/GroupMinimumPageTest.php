<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Actions\SetGroupMinimum;
use App\Modules\Tenancy\Contracts\Companies;
use App\Modules\Tenancy\Contracts\GroupMinimums;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SetGroupMinimum::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    $this->owner = ownerOf($this->tenant);
});

it('lives at /admin/{company}/privacy', function (): void {
    expect(route('tenant.privacy.edit', 'acme', absolute: false))->toBe('/admin/acme/privacy')
        ->and(route('tenant.privacy.update', 'acme', absolute: false))->toBe('/admin/acme/privacy');
});

it('sends guests to the login', function (): void {
    $this->get(route('tenant.privacy.edit', 'acme'))->assertRedirect(route('login'));
});

it('shows the owner the minimum the company applies, and that it follows the platform', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.privacy.edit', $this->tenant))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tenancy/privacy')
            ->where('minimum', 5)
            ->where('chosen', null)
            ->where('recommended', 5)
            ->where('lowest', 1)
            ->where('highest', 50));
});

it('shows the minimum the company chose', function (): void {
    $this->tenant->forceFill(['min_group' => 8])->save();

    $this->actingAs($this->owner)
        ->get(route('tenant.privacy.edit', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page->where('minimum', 8)->where('chosen', 8)->etc());
});

it('sends the page nothing but the numbers of the minimum: no answer, no person', function (): void {
    $this->actingAs($this->owner)
        ->get(route('tenant.privacy.edit', $this->tenant))
        ->assertInertia(fn (Assert $page) => $page
            ->where('minimum', fn (mixed $value): bool => is_int($value))
            ->where('chosen', null)
            ->where('recommended', fn (mixed $value): bool => is_int($value))
            ->where('lowest', 1)
            ->where('highest', 50)
            ->missing('answers')
            ->missing('responses'));
});

it('lets the owner change the minimum, and records who did it', function (): void {
    Log::spy();

    $this->actingAs($this->owner)
        ->put(route('tenant.privacy.update', $this->tenant), ['min_group' => 3])
        ->assertRedirect(route('tenant.privacy.edit', $this->tenant))
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Mínimo por grupo guardado.']]);

    expect($this->tenant->fresh()->min_group)->toBe(3);

    Log::shouldHaveReceived('info')->once()->with('tenant.group_minimum_changed', ['tenant_id' => $this->tenant->id, 'from' => null, 'to' => 3]);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('tenant.group_minimum_changed')
        ->and($entry->tenant_id)->toBe($this->tenant->id)
        ->and($entry->subject_id)->toBe($this->tenant->id)
        ->and($entry->metadata)->toEqual(['from' => null, 'to' => 3]);
});

it('takes the exact bounds and no minimum at all', function (int $minimum): void {
    $this->actingAs($this->owner)->put(route('tenant.privacy.update', $this->tenant), ['min_group' => $minimum])->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->min_group)->toBe($minimum);
})->with([1, 50]);

it('goes back to the platform default when the owner sends none', function (): void {
    $this->tenant->forceFill(['min_group' => 9])->save();

    $this->actingAs($this->owner)->put(route('tenant.privacy.update', $this->tenant), ['min_group' => null])->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->min_group)->toBeNull()
        ->and(AuditEntry::query()->sole()->metadata)->toEqual(['from' => 9, 'to' => null]);
});

it('records nothing when the minimum stays the same', function (?int $minimum): void {
    $this->tenant->forceFill(['min_group' => $minimum])->save();
    Log::spy();

    $this->actingAs($this->owner)->put(route('tenant.privacy.update', $this->tenant), ['min_group' => $minimum])->assertRedirect();

    expect(AuditEntry::query()->count())->toBe(0);
    Log::shouldNotHaveReceived('info', ['tenant.group_minimum_changed', Mockery::any()]);
})->with(['a chosen one' => 7, 'the platform\'s' => null]);

it('refuses a minimum outside 1 to 50, or not a whole number', function (mixed $minimum): void {
    $this->actingAs($this->owner)
        ->from(route('tenant.privacy.edit', $this->tenant))
        ->put(route('tenant.privacy.update', $this->tenant), ['min_group' => $minimum])
        ->assertSessionHasErrors('min_group');

    expect($this->tenant->fresh()->min_group)->toBeNull()
        ->and(AuditEntry::query()->count())->toBe(0);
})->with([0, 51, -3, 2.5, 'cinco']);

it('names the field in Spanish in its errors', function (): void {
    $this->actingAs($this->owner)
        ->put(route('tenant.privacy.update', $this->tenant), ['min_group' => 51])
        ->assertSessionHasErrors(['min_group' => 'El campo mínimo de personas por grupo no debe ser mayor que 50.']);
});

it('refuses RH and a viewer, on the page and before validating a change', function (TenantRole $role): void {
    $this->actingAs(memberOf($this->tenant, $role));

    $this->get(route('tenant.privacy.edit', $this->tenant))->assertForbidden();
    $this->from('/')->put(route('tenant.privacy.update', $this->tenant), ['min_group' => 99])
        ->assertForbidden()
        ->assertSessionHasNoErrors();

    expect($this->tenant->fresh()->min_group)->toBeNull();
})->with([TenantRole::Hr, TenantRole::Viewer]);

it('answers 404 to the owner of another company, by its URL', function (): void {
    $other = Tenant::factory()->create(['slug' => 'otra']);
    $this->actingAs(ownerOf($other));

    $this->get(route('tenant.privacy.edit', $this->tenant))->assertNotFound();
    $this->put(route('tenant.privacy.update', $this->tenant), ['min_group' => 2])->assertNotFound();

    expect($this->tenant->fresh()->min_group)->toBeNull();
});

it('changes only the owner\'s own company', function (): void {
    $other = Tenant::factory()->create(['min_group' => 12]);

    $this->actingAs($this->owner)->put(route('tenant.privacy.update', $this->tenant), ['min_group' => 2])->assertSessionHasNoErrors();

    expect($other->fresh()->min_group)->toBe(12)
        ->and($this->tenant->fresh()->min_group)->toBe(2);
});

it('is not a page of a union, which has no anonymous surveys', function (): void {
    $union = Tenant::factory()->union()->create(['slug' => 'sindicato']);
    $this->actingAs(ownerOf($union));

    $this->get(route('tenant.privacy.edit', $union))->assertNotFound();
});

it('drops what the request had read, so what launches next uses the new minimum', function (): void {
    resolve(Companies::class)->enter($this->tenant->id);
    expect(resolve(GroupMinimums::class)->current())->toBe(5);

    resolve(SetGroupMinimum::class)->handle($this->tenant, 2);

    expect(resolve(GroupMinimums::class)->current())->toBe(2);
});

it('refuses in the action a minimum outside the bounds', function (int $minimum): void {
    expect(fn () => resolve(SetGroupMinimum::class)->handle($this->tenant, $minimum))->toThrow(InvalidArgumentException::class);

    expect($this->tenant->fresh()->min_group)->toBeNull();
})->with([0, 51]);
