<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Tenancy\Actions\UpdateWorkCenter;
use App\Modules\Tenancy\Http\Controllers\WorkCenterController;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Support\Facades\Log;
use Inertia\Support\SessionKey;

mutates(UpdateWorkCenter::class, WorkCenterController::class);

function existingWorkCenter(?Tenant $tenant = null): WorkCenter
{
    return WorkCenter::factory()->for($tenant ?? Tenant::factory()->create())->create(workCenterForm(['state' => 'QUE']));
}

it('lives at /admin/{slug}/work-centers/{id}', function (): void {
    expect(route('tenant.work-centers.update', ['tenant' => 'acme', 'workCenter' => 'abc'], absolute: false))->toBe('/admin/acme/work-centers/abc');
});

it('sends guests to the login', function (): void {
    $workCenter = existingWorkCenter();

    $this->put(route('tenant.work-centers.update', [$workCenter->tenant, $workCenter->id]), workCenterForm(['name' => 'Otro']))
        ->assertRedirect(route('login'));

    expect(reloaded($workCenter)->name)->toBe('Planta Querétaro');
});

it('corrects the data of a work center', function (): void {
    $workCenter = existingWorkCenter();

    $this->actingAs(ownerOf($workCenter->tenant))
        ->put(route('tenant.work-centers.update', [$workCenter->tenant, $workCenter->id]), workCenterForm([
            'name' => 'Planta El Marqués',
            'interior_number' => '',
            'municipality' => 'El Marqués',
        ]))
        ->assertRedirect(route('tenant.work-centers.index', $workCenter->tenant))
        ->assertSessionHasNoErrors()
        ->assertSessionHas(SessionKey::FLASH_DATA, ['toast' => ['type' => 'success', 'message' => 'Centro de trabajo actualizado.']]);

    expect(reloaded($workCenter)->only(['name', 'interior_number', 'municipality', 'street']))->toBe([
        'name' => 'Planta El Marqués',
        'interior_number' => null,
        'municipality' => 'El Marqués',
        'street' => 'Av. 5 de Febrero',
    ]);
});

it('keeps its own name, in another case too, without calling it taken', function (): void {
    $workCenter = existingWorkCenter();

    $this->actingAs(ownerOf($workCenter->tenant))
        ->put(route('tenant.work-centers.update', [$workCenter->tenant, $workCenter->id]), workCenterForm(['name' => 'PLANTA QUERÉTARO']))
        ->assertSessionHasNoErrors();

    expect(reloaded($workCenter)->name)->toBe('PLANTA QUERÉTARO');
});

it('rejects the name of another work center of the company', function (): void {
    $workCenter = existingWorkCenter();
    WorkCenter::factory()->for($workCenter->tenant)->create(['name' => 'Oficinas Centrales']);

    $this->actingAs(ownerOf($workCenter->tenant))
        ->put(route('tenant.work-centers.update', [$workCenter->tenant, $workCenter->id]), workCenterForm(['name' => 'oficinas centrales']))
        ->assertSessionHasErrors(['name' => 'Ya hay un centro de trabajo con este nombre.']);

    expect(reloaded($workCenter)->name)->toBe('Planta Querétaro');
});

it('validates the fields like the creation does', function (): void {
    $workCenter = existingWorkCenter();

    $this->actingAs(ownerOf($workCenter->tenant))
        ->put(route('tenant.work-centers.update', [$workCenter->tenant, $workCenter->id]), workCenterForm(['street' => '', 'postal_code' => '1', 'state' => 'XX']))
        ->assertSessionHasErrors(['street', 'postal_code', 'state']);
});

it('logs the names of the changed fields, in alphabetical order, never their values', function (): void {
    $workCenter = existingWorkCenter();
    $this->travel(1)->minutes();
    Log::spy();

    $this->actingAs(ownerOf($workCenter->tenant))
        ->put(route('tenant.work-centers.update', [$workCenter->tenant, $workCenter->id]), workCenterForm(['street' => 'Calle Nueva', 'name' => 'Planta Nueva']))
        ->assertSessionHasNoErrors();

    Log::shouldHaveReceived('info')->once()->with('work_center.updated', ['work_center_id' => $workCenter->id, 'changed' => ['name', 'street']]);

    expect(AuditEntry::query()->count())->toBe(0);
});

it('logs nothing when nothing changed', function (): void {
    $workCenter = existingWorkCenter();
    Log::spy();

    $this->actingAs(ownerOf($workCenter->tenant))
        ->put(route('tenant.work-centers.update', [$workCenter->tenant, $workCenter->id]), workCenterForm())
        ->assertRedirect(route('tenant.work-centers.index', $workCenter->tenant));

    Log::shouldNotHaveReceived('info');
});

it('refuses to edit an archived work center', function (): void {
    $workCenter = WorkCenter::factory()->archived()->create(['name' => 'Bodega']);

    $this->actingAs(ownerOf($workCenter->tenant))
        ->put(route('tenant.work-centers.update', [$workCenter->tenant, $workCenter->id]), workCenterForm(['name' => 'Bodega Nueva']))
        ->assertConflict();

    expect(reloaded($workCenter)->name)->toBe('Bodega');
});

it('answers 404 to a work center of another company, even through the own company URL', function (): void {
    $foreign = existingWorkCenter();
    $tenant = Tenant::factory()->create();
    $owner = ownerOf($tenant);

    $this->actingAs($owner)
        ->put(route('tenant.work-centers.update', [$tenant, $foreign->id]), workCenterForm(['name' => 'Robado']))
        ->assertNotFound();

    $this->actingAs($owner)
        ->put(route('tenant.work-centers.update', [$foreign->tenant, $foreign->id]), workCenterForm(['name' => 'Robado']))
        ->assertNotFound();

    expect(reloaded($foreign)->name)->toBe('Planta Querétaro');
});

it('answers 404 to an id that is not a UUID or does not exist', function (string $id): void {
    $tenant = Tenant::factory()->create();

    $this->actingAs(ownerOf($tenant))
        ->put("/admin/{$tenant->slug}/work-centers/{$id}", workCenterForm())
        ->assertNotFound();
})->with(['not-a-uuid', '0198c3a2-0000-4000-8000-000000000000']);
