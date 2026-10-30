<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Actions\CreateWorkCenter;
use App\Modules\Tenancy\Enums\MexicanState;
use App\Modules\Tenancy\Http\Requests\WorkCenterRequest;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(CreateWorkCenter::class, WorkCenterRequest::class);

/** Every work center in the table, whatever its company. */
function allWorkCenters(): WorkCenter
{
    return WorkCenter::query()->withoutGlobalScopes()->sole();
}

it('lives at /admin/{slug}/work-centers', function (): void {
    expect(route('tenant.work-centers.store', ['tenant' => 'acme'], absolute: false))->toBe('/admin/acme/work-centers');
});

it('sends guests to the login and stores nothing', function (): void {
    $tenant = Tenant::factory()->create();

    $this->post(route('tenant.work-centers.store', $tenant), workCenterForm())->assertRedirect(route('login'));

    expect(WorkCenter::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('adds a work center to the company in the URL', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['name' => '  Planta Querétaro  ']))
        ->assertRedirect(route('tenant.work-centers.index', $tenant))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Centro de trabajo dado de alta.']]);

    $workCenter = allWorkCenters();

    expect($workCenter->tenant_id)->toBe($tenant->id)
        ->and($workCenter->only(['name', 'street', 'exterior_number', 'interior_number', 'neighborhood', 'postal_code', 'municipality', 'main_activity']))->toBe([
            'name' => 'Planta Querétaro',
            'street' => 'Av. 5 de Febrero',
            'exterior_number' => '1200',
            'interior_number' => 'Nave 3',
            'neighborhood' => 'Zona Industrial Benito Juárez',
            'postal_code' => '76120',
            'municipality' => 'Querétaro',
            'main_activity' => 'Manufactura de autopartes',
        ])
        ->and($workCenter->state)->toBe(MexicanState::Queretaro)
        ->and($workCenter->archived_at)->toBeNull();
});

it('stores an empty interior number as none', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['interior_number' => '']))
        ->assertSessionHasNoErrors();

    expect(allWorkCenters()->interior_number)->toBeNull();
});

it('never trusts a company sent in the body', function (): void {
    $tenant = Tenant::factory()->create();
    $other = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['tenant_id' => $other->id]))
        ->assertSessionHasNoErrors();

    expect(allWorkCenters()->tenant_id)->toBe($tenant->id);
});

it('requires every field but the interior number', function (): void {
    $tenant = Tenant::factory()->create();
    $required = ['name', 'street', 'exterior_number', 'neighborhood', 'postal_code', 'municipality', 'state', 'main_activity'];

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(array_fill_keys([...$required, 'interior_number'], '')))
        ->assertSessionHasErrors($required)
        ->assertSessionDoesntHaveErrors('interior_number');

    expect(WorkCenter::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('rejects a postal code that is not five digits', function (string $postalCode): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['postal_code' => $postalCode]))
        ->assertSessionHasErrors(['postal_code' => 'Escribe un código postal de 5 dígitos.']);
})->with(['1234', '123456', '7612A', '76 120']);

it('accepts only one of the 32 states', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['state' => 'XYZ']))
        ->assertSessionHasErrors(['state' => 'Elige un estado de la lista.']);
});

it('limits the length of each field', function (string $field, int $max): void {
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);

    $this->actingAs($owner)
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm([$field => str_repeat('a', $max + 1)]))
        ->assertSessionHasErrors([$field]);

    $this->actingAs($owner)
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm([$field => str_repeat('a', $max)]))
        ->assertSessionHasNoErrors();
})->with([
    ['name', 120], ['street', 150], ['exterior_number', 20], ['interior_number', 20],
    ['neighborhood', 120], ['municipality', 120], ['main_activity', 200],
]);

it('rejects a line break or a control character', function (string $field): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm([$field => "Planta\nNorte"]))
        ->assertSessionHasErrors([$field]);
})->with(['name', 'street', 'exterior_number', 'interior_number', 'neighborhood', 'municipality', 'main_activity']);

it('rejects a name the company already uses, whatever the case, archived ones included', function (bool $archived): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->for($tenant)->state(['archived_at' => $archived ? now() : null])->create(['name' => 'Planta Querétaro']);

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['name' => 'PLANTA QUERÉTARO']))
        ->assertSessionHasErrors(['name' => 'Ya hay un centro de trabajo con este nombre.']);
})->with(['active' => false, 'archived' => true]);

it('lets a company use a name another company uses', function (): void {
    $tenant = Tenant::factory()->create();
    WorkCenter::factory()->create(['name' => 'Planta Querétaro']);

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm(['name' => 'Planta Querétaro']))
        ->assertSessionHasNoErrors();
});

it('logs the new work center by id and leaves the audit trail alone', function (): void {
    $tenant = Tenant::factory()->create();
    Log::spy();

    $this->actingAs(ownerOf($tenant))->post(route('tenant.work-centers.store', $tenant), workCenterForm());

    Log::shouldHaveReceived('info')->once()->with('work_center.created', ['work_center_id' => allWorkCenters()->id]);

    expect(AuditEntry::query()->count())->toBe(0);
});

it('answers 404 to a member of another company and stores nothing', function (): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf(Tenant::factory()->create()))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm())
        ->assertNotFound();

    expect(WorkCenter::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('refuses a suspended company with 423 and stores nothing', function (): void {
    $tenant = Tenant::factory()->deactivated()->create();

    $this->actingAs(ownerOf($tenant))
        ->post(route('tenant.work-centers.store', $tenant), workCenterForm())
        ->assertStatus(423);

    expect(WorkCenter::query()->withoutGlobalScopes()->count())->toBe(0);
});
