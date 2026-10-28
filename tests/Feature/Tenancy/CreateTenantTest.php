<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Models\User;
use App\Modules\Tenancy\Actions\CreateTenant;
use App\Modules\Tenancy\Enums\TenantStatus;
use App\Modules\Tenancy\Http\Requests\StoreTenantRequest;
use App\Modules\Tenancy\Http\Requests\TenantRules;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Support\SessionKey;

mutates(CreateTenant::class, StoreTenantRequest::class, TenantRules::class);

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newCompanyInput(array $overrides = []): array
{
    return [
        'legal_name' => 'Grupo Ejemplo S.A. de C.V.',
        'trade_name' => 'Grupo Ejemplo',
        'rfc' => 'GEJ0101011A1',
        'slug' => 'grupo-ejemplo',
        ...$overrides,
    ];
}

it('lives at /admin/companies', function (): void {
    expect(route('companies.store', absolute: false))->toBe('/admin/companies');
});

it('sends guests to the login', function (): void {
    $this->post(route('companies.store'), newCompanyInput())->assertRedirect(route('login'));

    expect(Tenant::query()->count())->toBe(0);
});

it('creates a company that has not started its trial', function (): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput())
        ->assertRedirect(route('companies.index'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Empresa dada de alta.']]);

    $tenant = Tenant::query()->sole();

    expect($tenant->legal_name)->toBe('Grupo Ejemplo S.A. de C.V.')
        ->and($tenant->trade_name)->toBe('Grupo Ejemplo')
        ->and($tenant->rfc)->toBe('GEJ0101011A1')
        ->and($tenant->slug)->toBe('grupo-ejemplo')
        ->and($tenant->status())->toBe(TenantStatus::NotStarted)
        ->and(Str::isUuid($tenant->id, 4))->toBeTrue();
});

it('normalizes the RFC, the slug and the names before checking them', function (): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput([
            'legal_name' => '  Grupo Ejemplo S.A. de C.V.  ',
            'trade_name' => "\tGrupo Ejemplo ",
            'rfc' => ' gej010101 1a1 ',
            'slug' => ' Grupo-Ejemplo ',
        ]))
        ->assertSessionHasNoErrors();

    $tenant = Tenant::query()->sole();

    expect($tenant->rfc)->toBe('GEJ0101011A1')
        ->and($tenant->slug)->toBe('grupo-ejemplo')
        ->and($tenant->legal_name)->toBe('Grupo Ejemplo S.A. de C.V.')
        ->and($tenant->trade_name)->toBe('Grupo Ejemplo');
});

it('normalizes the RFC and the slug, leaving the names to TrimStrings and what is not text to the validation', function (): void {
    expect(TenantRules::normalize([
        'legal_name' => ['x'],
        'trade_name' => "\t Grupo ",
        'rfc' => " gej010101\t1a1\n",
        'slug' => ' Grupo-Ejemplo ',
        'other' => ' Kept ',
    ]))->toBe([
        'legal_name' => ['x'],
        'trade_name' => "\t Grupo ",
        'rfc' => 'GEJ0101011A1',
        'slug' => 'grupo-ejemplo',
        'other' => ' Kept ',
    ]);
});

it('accepts the RFC of a company and of a person', function (string $rfc): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput(['rfc' => $rfc]))
        ->assertSessionHasNoErrors();

    expect(Tenant::query()->sole()->rfc)->toBe($rfc);
})->with([
    'company, 12 characters' => 'GEJ0101011A1',
    'person, 13 characters, born on a leap day' => 'GOMA840229AB3',
    'with Ñ and &' => 'Ñ&A0101011A1',
    'born on the leap day of 2000' => 'GOMA000229AB3',
    'born on the leap day of 1900-something' => 'GOMA960229AB3',
]);

it('rejects a malformed RFC', function (string $rfc): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput(['rfc' => $rfc]))
        ->assertSessionHasErrors('rfc');

    expect(Tenant::query()->count())->toBe(0);
})->with([
    '11 characters' => 'GEJ0101011A',
    '14 characters' => 'GOMA840229AB3X',
    'month 13' => 'GEJ0113011A1',
    'day 32' => 'GEJ0101321A1',
    'a 29 of February that never existed' => 'GOMA850229AB3',
    'letters in the date' => 'GEJ01O1011A1',
    'a symbol in the homoclave' => 'GEJ0101011A-',
]);

it('says which date does not exist in the RFC', function (): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput(['rfc' => 'GEJ0113011A1']))
        ->assertSessionHasErrors(['rfc' => 'El RFC tiene una fecha que no existe.']);
});

it('rejects an RFC another company already has, whatever its case', function (): void {
    Tenant::factory()->create(['rfc' => 'GEJ0101011A1', 'slug' => 'otra-empresa']);

    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput(['rfc' => 'gej0101011a1']))
        ->assertSessionHasErrors(['rfc' => 'Ya hay una empresa con este RFC.']);

    expect(Tenant::query()->count())->toBe(1);
});

it('rejects a duplicated, reserved or malformed slug', function (string $slug, string $error): void {
    Tenant::factory()->create(['rfc' => 'AAA010101AAA', 'slug' => 'grupo-ejemplo']);

    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput(['slug' => $slug]))
        ->assertSessionHasErrors(['slug' => $error]);

    expect(Tenant::query()->count())->toBe(1);
})->with([
    'taken' => ['grupo-ejemplo', 'Esa dirección ya la usa otra empresa.'],
    'reserved' => ['settings', 'Esa dirección está reservada. Elige otra.'],
    'spaces' => ['Grupo Ejemplo', 'El formato del campo dirección no es válido.'],
    'too short' => ['ab', 'El campo dirección debe tener entre 3 y 50 caracteres.'],
    '51 characters' => [str_repeat('a', 51), 'El campo dirección debe tener entre 3 y 50 caracteres.'],
    'leading hyphen' => ['-acme', 'El formato del campo dirección no es válido.'],
    'trailing hyphen' => ['acme-', 'El formato del campo dirección no es válido.'],
    'double hyphen' => ['ac--me', 'El formato del campo dirección no es válido.'],
    'underscore' => ['ac_me', 'El formato del campo dirección no es válido.'],
]);

it('accepts a slug of exactly 3 and 50 characters', function (string $slug): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput(['slug' => $slug]))
        ->assertSessionHasNoErrors();

    expect(Tenant::query()->sole()->slug)->toBe($slug);
})->with(['3' => 'abc', '50' => str_repeat('a', 50)]);

it('requires every field', function (string $field, string $error): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput([$field => '']))
        ->assertSessionHasErrors([$field => $error]);

    expect(Tenant::query()->count())->toBe(0);
})->with([
    ['legal_name', 'El campo razón social es obligatorio.'],
    ['trade_name', 'El campo nombre comercial es obligatorio.'],
    ['rfc', 'El campo RFC es obligatorio.'],
    ['slug', 'El campo dirección es obligatorio.'],
]);

it('limits the names', function (string $field, int $max): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput([$field => str_repeat('a', $max)]))
        ->assertSessionHasNoErrors();

    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput([$field => str_repeat('a', $max + 1), 'rfc' => 'AAA010101AAA', 'slug' => 'otra']))
        ->assertSessionHasErrors($field);
})->with([['legal_name', 200], ['trade_name', 120]]);

it('rejects a name with a line break', function (string $field): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput([$field => "Grupo\nEjemplo"]))
        ->assertSessionHasErrors($field);
})->with(['legal_name', 'trade_name']);

it('rejects a field that is not text', function (string $field): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput([$field => ['x']]))
        ->assertSessionHasErrors($field);

    expect(Tenant::query()->count())->toBe(0);
})->with(['legal_name', 'trade_name', 'rfc', 'slug']);

it('says that a field must be text', function (string $field, string $name): void {
    $this->actingAs(superadmin())
        ->post(route('companies.store'), newCompanyInput([$field => ['x']]))
        ->assertSessionHasErrors([$field => "El campo {$name} debe ser una cadena de texto."]);
})->with([
    ['legal_name', 'razón social'],
    ['trade_name', 'nombre comercial'],
    ['rfc', 'RFC'],
    ['slug', 'dirección'],
]);

it('logs and audits the creation without the company data', function (): void {
    Log::spy();

    $this->actingAs(superadmin())->post(route('companies.store'), newCompanyInput());

    $id = Tenant::query()->sole()->id;

    Log::shouldHaveReceived('info')->once()->with('tenant.created', ['tenant_id' => $id]);

    $entry = AuditEntry::query()->sole();

    expect($entry->event)->toBe('tenant.created')
        ->and($entry->subject_id)->toBe($id)
        ->and($entry->tenant_id)->toBeNull()
        ->and($entry->metadata)->toBe([]);
});

it('answers 404 to anyone who is not a superadmin, even with an invalid body', function (array $body): void {
    $this->actingAs(User::factory()->create())
        ->post(route('companies.store'), $body)
        ->assertNotFound();

    expect(Tenant::query()->count())->toBe(0)
        ->and(AuditEntry::query()->count())->toBe(0);
})->with(['valid' => [newCompanyInput()], 'invalid' => [[]]]);

it('writes the same slug as Laravel for the same name', function (string $name, string $slug): void {
    expect(Str::slug($name))->toBe($slug);
})->with([
    ['Grupo Acme, S.A.', 'grupo-acme-sa'],
    ['Ñandú Café', 'nandu-cafe'],
    ['  --Hola   Mundo--  ', 'hola-mundo'],
    ['', ''],
]);
