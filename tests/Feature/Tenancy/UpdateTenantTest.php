<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\UpdateTenant;
use App\Modules\Tenancy\Http\Requests\UpdateTenantRequest;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(UpdateTenant::class, UpdateTenantRequest::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function editedCompany(array $overrides = []): array
{
    return [
        'legal_name' => 'Grupo Ejemplo S.A. de C.V.',
        'trade_name' => 'Grupo Ejemplo',
        'rfc' => 'GEJ0101011A1',
        'slug' => 'grupo-ejemplo',
        ...$overrides,
    ];
}

function existingCompany(): Tenant
{
    return Tenant::factory()->create(editedCompany());
}

it('lives at /admin/companies/{tenant}', function (): void {
    expect(route('companies.update', 'abc', absolute: false))->toBe('/admin/companies/abc');
});

it('sends guests to the login', function (): void {
    $tenant = existingCompany();

    $this->put(route('companies.update', $tenant), editedCompany(['trade_name' => 'Otro']))->assertRedirect(route('login'));

    expect($tenant->refresh()->trade_name)->toBe('Grupo Ejemplo');
});

it('updates the company data', function (): void {
    $tenant = existingCompany();

    $this->actingAs(superadmin())
        ->put(route('companies.update', $tenant), editedCompany([
            'legal_name' => '  Ejemplo Nuevo S.A.  ',
            'trade_name' => 'Ejemplo Nuevo',
            'rfc' => 'ejn 0202022b2',
            'slug' => 'Ejemplo-Nuevo',
        ]))
        ->assertRedirect(route('companies.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empresa actualizada.']]);

    $tenant->refresh();

    expect($tenant->legal_name)->toBe('Ejemplo Nuevo S.A.')
        ->and($tenant->trade_name)->toBe('Ejemplo Nuevo')
        ->and($tenant->rfc)->toBe('EJN0202022B2')
        ->and($tenant->slug)->toBe('ejemplo-nuevo');
});

it('keeps its own RFC and slug without calling them duplicated', function (): void {
    $tenant = existingCompany();

    $this->actingAs(superadmin())
        ->put(route('companies.update', $tenant), editedCompany(['legal_name' => 'Grupo Ejemplo Corregido']))
        ->assertSessionHasNoErrors();

    expect($tenant->refresh()->legal_name)->toBe('Grupo Ejemplo Corregido');
});

it('rejects the RFC or slug of another company', function (string $field, string $value, string $error): void {
    Tenant::factory()->create(['rfc' => 'AAA010101AAA', 'slug' => 'otra-empresa']);
    $tenant = existingCompany();

    $this->actingAs(superadmin())
        ->put(route('companies.update', $tenant), editedCompany([$field => $value]))
        ->assertSessionHasErrors([$field => $error]);

    expect($tenant->refresh()->only('rfc', 'slug'))->toBe(['rfc' => 'GEJ0101011A1', 'slug' => 'grupo-ejemplo']);
})->with([
    'rfc' => ['rfc', 'aaa010101aaa', 'Ya hay una empresa con este RFC.'],
    'slug' => ['slug', 'otra-empresa', 'Esa dirección ya la usa otra empresa.'],
]);

it('validates the fields like the creation does', function (): void {
    $tenant = existingCompany();

    $this->actingAs(superadmin())
        ->put(route('companies.update', $tenant), editedCompany(['legal_name' => '', 'rfc' => 'GEJ0113011A1', 'slug' => 'settings']))
        ->assertSessionHasErrors(['legal_name', 'rfc', 'slug']);
});

it('does not change the slug when the trade name changes', function (): void {
    $tenant = existingCompany();

    $this->actingAs(superadmin())
        ->put(route('companies.update', $tenant), editedCompany(['trade_name' => 'Nombre Distinto']))
        ->assertSessionHasNoErrors();

    expect($tenant->refresh()->trade_name)->toBe('Nombre Distinto')
        ->and($tenant->slug)->toBe('grupo-ejemplo');
});

it('logs and audits the names of the changed fields, never their values', function (): void {
    $tenant = existingCompany();
    Log::spy();

    $this->actingAs(superadmin())
        ->put(route('companies.update', $tenant), editedCompany(['slug' => 'nueva-direccion', 'rfc' => 'NUE0303033C3']));

    Log::shouldHaveReceived('info')->once()->with('tenant.updated', ['tenant_id' => $tenant->id, 'changed' => ['rfc', 'slug']]);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('tenant.updated')
        ->and($entry->subject_id)->toBe($tenant->id)
        ->and($entry->metadata)->toBe(['changed' => ['rfc', 'slug']])
        ->and(json_encode($entry->toArray()))->not->toContain('NUE0303033C3');
});

it('lists the changed fields in alphabetical order, as a list, without the update time', function (): void {
    $tenant = existingCompany();
    $this->travel(1)->minutes();
    Log::spy();

    // The table stores trade_name before rfc: the list comes out sorted, not in column order.
    $this->actingAs(superadmin())
        ->put(route('companies.update', $tenant), editedCompany(['trade_name' => 'Nombre Nuevo', 'rfc' => 'NUE0303033C3']))
        ->assertSessionHasNoErrors();

    Log::shouldHaveReceived('info')->once()->with('tenant.updated', ['tenant_id' => $tenant->id, 'changed' => ['rfc', 'trade_name']]);

    expect(AuditEntry::query()->sole()->metadata)->toBe(['changed' => ['rfc', 'trade_name']]);
});

it('records nothing when nothing changed', function (): void {
    $tenant = existingCompany();
    Log::spy();

    $this->actingAs(superadmin())
        ->put(route('companies.update', $tenant), editedCompany())
        ->assertRedirect(route('companies.index'));

    Log::shouldNotHaveReceived('info');

    expect(AuditEntry::query()->count())->toBe(0);
});

it('answers 404 to a non-superadmin and to an unknown company', function (): void {
    $tenant = existingCompany();

    $this->actingAs(User::factory()->create())
        ->put(route('companies.update', $tenant), editedCompany(['trade_name' => 'Otro']))
        ->assertNotFound();

    $this->actingAs(superadmin())
        ->put(route('companies.update', '0198c3a2-0000-4000-8000-000000000000'), editedCompany())
        ->assertNotFound();

    expect($tenant->refresh()->trade_name)->toBe('Grupo Ejemplo')
        ->and(AuditEntry::query()->count())->toBe(0);
});
