<?php

declare(strict_types=1);

use App\Modules\Telework\Enums\DocumentKind;
use App\Modules\Telework\Http\Controllers\DocumentController;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Telework\Models\TeleworkDocument;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

mutates(DocumentController::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.telework_files'));
    $this->tenant = Tenant::factory()->create();
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
    $this->north = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->south = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Sur']);
    $this->actingAs($this->hr);
});

/** A real upload (not the fake one, whose type comes from its extension): the server sniffs the content. */
function documentFile(string $content = "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n", string $name = 'politica.pdf'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'doc');
    file_put_contents($path, $content);

    return new UploadedFile($path, $name, null, null, true);
}

/** @param array<string, mixed> $overrides */
function uploadDocument(array $overrides = []): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.telework.documents.store', test()->tenant), [
        'kind' => 'policy',
        'document' => documentFile(),
        'work_center_ids' => [test()->north->id],
        ...$overrides,
    ]);
}

function stored(DocumentKind $kind, WorkCenter $center, int $version = 1, array $items = []): TeleworkDocument
{
    $document = TeleworkDocument::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'kind' => $kind, 'version' => $version, 'covered_items' => $items]);
    Storage::disk(config()->string('filesystems.telework_files'))->put($document->path, "%PDF-1.4\n%%EOF\n");

    return $document;
}

it('accepts only PDFs under the size limit, by content', function (): void {
    uploadDocument(['document' => documentFile('just text', 'politica.pdf')])->assertSessionHasErrors('document');
    uploadDocument(['document' => documentFile("\x89PNG\r\n\x1a\n", 'politica.pdf')])->assertSessionHasErrors('document');
    uploadDocument(['document' => null])->assertSessionHasErrors('document');

    config(['telework.max_document_kb' => 1]);
    uploadDocument(['document' => documentFile("%PDF-1.4\n".str_repeat('x', 2048)."\n%%EOF\n")])->assertSessionHasErrors('document');

    workIn($this->tenant);
    expect(TeleworkDocument::query()->count())->toBe(0);

    config(['telework.max_document_kb' => 20480]);
    uploadDocument()->assertSessionHasNoErrors();
});

it('publishes a version for each chosen center and goes back to the documents', function (): void {
    uploadDocument(['work_center_ids' => [$this->north->id, $this->south->id], 'covered_items' => ['a', 'b']])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('tenant.telework.documents.index', $this->tenant));

    workIn($this->tenant);
    $documents = TeleworkDocument::query()->get();
    expect($documents)->toHaveCount(2)
        ->and($documents->pluck('version')->unique()->all())->toBe([1])
        ->and($documents->pluck('path')->unique())->toHaveCount(1)
        ->and($documents[0]->covered_items)->toBe(['a', 'b']);
});

it('publishes a new version over the old one without deleting it', function (): void {
    uploadDocument()->assertSessionHasNoErrors();
    uploadDocument(['document' => documentFile("%PDF-1.4\n%%EOF\n second")])->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(TeleworkDocument::query()->orderBy('version')->pluck('version')->all())->toBe([1, 2]);
});

it('takes every active center when asked for all of them', function (): void {
    WorkCenter::factory()->for($this->tenant)->create(['archived_at' => now()]);

    uploadDocument(['work_center_ids' => [], 'all_centers' => '1'])->assertSessionHasNoErrors();

    workIn($this->tenant);
    expect(TeleworkDocument::query()->pluck('work_center_id')->sort()->values()->all())->toBe(collect([$this->north->id, $this->south->id])->sort()->values()->all());
});

it('rejects covered items that do not belong to the kind', function (): void {
    uploadDocument(['kind' => 'reversibility', 'covered_items' => ['a']])->assertSessionHasErrors('covered_items');
    uploadDocument(['covered_items' => ['m']])->assertSessionHasErrors('covered_items');
    uploadDocument(['covered_items' => 'a'])->assertSessionHasErrors('covered_items');
    uploadDocument(['kind' => 'nope'])->assertSessionHasErrors('kind');

    workIn($this->tenant);
    expect(TeleworkDocument::query()->count())->toBe(0);
});

it('rejects no centers, a center of another company or an archived one', function (): void {
    $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
    $archived = WorkCenter::factory()->for($this->tenant)->create(['archived_at' => now()]);

    uploadDocument(['work_center_ids' => []])->assertSessionHasErrors('work_center_ids');
    uploadDocument(['work_center_ids' => [$foreign->id]])->assertSessionHasErrors('work_center_ids');
    uploadDocument(['work_center_ids' => [$archived->id]])->assertSessionHasErrors('work_center_ids');
    uploadDocument(['work_center_ids' => 'x'])->assertSessionHasErrors('work_center_ids');

    workIn($this->tenant);
    expect(TeleworkDocument::query()->count())->toBe(0);
});

it('names the fields in Spanish', function (): void {
    uploadDocument(['document' => null, 'kind' => ''])->assertSessionHasErrors(['document' => 'El campo documento es obligatorio.', 'kind' => 'El campo tipo de documento es obligatorio.']);
    uploadDocument(['work_center_ids' => []])->assertSessionHasErrors(['work_center_ids' => 'Elige al menos un centro de trabajo.']);
});

it('lets only owner and hr upload', function (TenantRole $role, int $status): void {
    $this->actingAs(memberOf($this->tenant, $role));

    uploadDocument()->assertStatus($status);
    workIn($this->tenant);
    expect(TeleworkDocument::query()->count())->toBe($status === 302 ? 1 : 0);
})->with([
    'owner' => [TenantRole::Owner, 302],
    'hr' => [TenantRole::Hr, 302],
    'viewer' => [TenantRole::Viewer, 403],
    'committee' => [TenantRole::Committee, 403],
]);

it('lists the latest version per kind and center with its missing items, and still publishes', function (): void {
    uploadDocument(['covered_items' => ['a', 'b']])->assertSessionHasNoErrors();
    uploadDocument(['covered_items' => ['a', 'b', 'c']])->assertSessionHasNoErrors();
    uploadDocument(['kind' => 'reversibility', 'work_center_ids' => [$this->south->id]])->assertSessionHasNoErrors();

    $this->get(route('tenant.telework.documents.index', $this->tenant))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('telework/documents')
        ->where('documents.total', 2)
        ->where('documents.data', function ($rows) {
            $rows = collect($rows)->keyBy('kind');

            expect($rows['reversibility']['workCenter']['name'])->toBe('Planta Sur')
                ->and($rows['reversibility']['coveredItems'])->toBe([])
                ->and($rows['reversibility']['missingItems'])->toBe([])
                ->and($rows['policy']['version'])->toBe(2)
                ->and($rows['policy']['workCenter']['name'])->toBe('Planta Norte')
                ->and($rows['policy']['coveredItems'])->toBe(['a', 'b', 'c'])
                ->and($rows['policy']['missingItems'])->toBe(['d', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l']);

            return true;
        })
        ->has('kinds', 8)
        ->where('kinds.0.value', 'policy')
        ->where('kinds.0.items.0', ['value' => 'a', 'label' => 'Cultura de prevención de riesgos de trabajo'])
        ->where('canManage', true));
});

it('shows every version when asked for the history', function (): void {
    uploadDocument()->assertSessionHasNoErrors();
    uploadDocument()->assertSessionHasNoErrors();

    $this->get(route('tenant.telework.documents.index', [$this->tenant, 'versions' => 'all']))->assertInertia(fn (Assert $page) => $page
        ->where('documents.total', 2)
        ->where('documents.data.0.version', 2)
        ->where('documents.data.1.version', 1)
        ->where('filters.versions', 'all'));
});

it('filters by work center', function (): void {
    stored(DocumentKind::Policy, $this->north);
    stored(DocumentKind::Policy, $this->south);

    $this->get(route('tenant.telework.documents.index', [$this->tenant, 'center' => $this->south->id]))->assertInertia(fn (Assert $page) => $page
        ->where('documents.total', 1)
        ->where('documents.data.0.workCenter.id', $this->south->id)
        ->where('filters.center', ['value' => $this->south->id, 'label' => 'Planta Sur']));
});

it('paginates the documents fifteen at a time', function (): void {
    foreach (range(1, 16) as $version) {
        stored(DocumentKind::Policy, $this->north, $version);
    }

    $this->get(route('tenant.telework.documents.index', [$this->tenant, 'versions' => 'all']))->assertInertia(fn (Assert $page) => $page
        ->where('documents.total', 16)->where('documents.per_page', 15)->has('documents.data', 15));
});

it('shows a committee user only the documents of its centers, and the others see them all', function (): void {
    stored(DocumentKind::Policy, $this->north);
    stored(DocumentKind::Policy, $this->south);
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'user_id' => $cora->id]);

    $this->actingAs($cora)->get(route('tenant.telework.documents.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('documents.total', 1)->where('documents.data.0.workCenter.id', $this->north->id)->where('canManage', false));
    // An unassigned center is no filter at all for the committee: it still sees only its own.
    $this->get(route('tenant.telework.documents.index', [$this->tenant, 'center' => $this->south->id]))->assertInertia(fn (Assert $page) => $page->where('documents.total', 1)->where('documents.data.0.workCenter.id', $this->north->id));

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.telework.documents.index', $this->tenant))->assertInertia(fn (Assert $page) => $page
        ->where('documents.total', 2));
});

it('does not list the documents of another company', function (): void {
    $other = Tenant::factory()->create();
    stored(DocumentKind::Policy, WorkCenter::factory()->for($other)->create());

    $this->get(route('tenant.telework.documents.index', $this->tenant))->assertInertia(fn (Assert $page) => $page->where('documents.total', 0));
});

it('serves the file to the company roles and only to the committee of that center', function (): void {
    $north = stored(DocumentKind::Policy, $this->north);
    $south = stored(DocumentKind::Policy, $this->south);
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'user_id' => $cora->id]);
    $url = fn (TeleworkDocument $document): string => route('tenant.telework.documents.file', [$this->tenant, $document->id]);

    foreach ([$this->hr, memberOf($this->tenant, TenantRole::Owner), memberOf($this->tenant, TenantRole::Viewer)] as $user) {
        $response = $this->actingAs($user)->get($url($south))->assertOk();
        expect((string) $response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
            ->and((string) $response->headers->get('Content-Type'))->toContain('application/pdf');
    }

    $this->actingAs($cora)->get($url($north))->assertOk();
    $this->actingAs($cora)->get($url($south))->assertNotFound();
});

it('does not serve the file of another company, an unknown one or a malformed id', function (): void {
    $foreign = stored(DocumentKind::Policy, WorkCenter::factory()->for(Tenant::factory()->create())->create());

    $this->get(route('tenant.telework.documents.file', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->get(route('tenant.telework.documents.file', [$this->tenant, (string) Str::uuid()]))->assertNotFound();
    $this->get(route('tenant.telework.documents.file', [$this->tenant, 'nope']))->assertNotFound();
});

it('never deletes: there is no way to remove or replace a document', function (): void {
    $document = stored(DocumentKind::Policy, $this->north);

    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (Illuminate\Routing\Route $route): bool => str_starts_with((string) $route->getName(), 'tenant.telework.documents.'));
    expect($routes->map(fn (Illuminate\Routing\Route $route): array => $route->methods())->flatten()->unique()->sort()->values()->all())->toBe(['GET', 'HEAD', 'POST']);

    $this->delete('/admin/'.$this->tenant->slug.'/telework/documents/'.$document->id.'/file')->assertStatus(405);
    $this->put('/admin/'.$this->tenant->slug.'/telework/documents/'.$document->id.'/file')->assertStatus(405);
    workIn($this->tenant);
    expect(TeleworkDocument::query()->count())->toBe(1);
});

it('offers the committee only its centers to search, owner and hr every active one, never another company\'s', function (): void {
    WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Archivada', 'archived_at' => now()]);
    WorkCenter::factory()->for(Tenant::factory()->create())->create(['name' => 'Planta Ajena']);
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'user_id' => $cora->id]);
    $options = fn (array $query = []): array => $this->getJson(route('tenant.telework.work-centers.options', [$this->tenant, ...$query]))->assertOk()->json();

    expect($options())->toBe([['value' => $this->north->id, 'label' => 'Planta Norte'], ['value' => $this->south->id, 'label' => 'Planta Sur']]);
    expect($options(['q' => 'sur']))->toBe([['value' => $this->south->id, 'label' => 'Planta Sur']]);

    $this->actingAs($cora);
    expect($options())->toBe([['value' => $this->north->id, 'label' => 'Planta Norte']])
        ->and($options(['q' => 'sur']))->toBe([]);

    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));
    expect($options())->toHaveCount(2);
});

it('does not name a center the committee user is not assigned to when filtering by it', function (): void {
    stored(DocumentKind::Policy, $this->south);
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->north->id, 'user_id' => $cora->id]);

    $this->actingAs($cora)->get(route('tenant.telework.documents.index', [$this->tenant, 'center' => $this->south->id]))->assertInertia(fn (Assert $page) => $page
        ->where('filters.center', null)->where('documents.total', 0)); // no documents of its own: nothing leaks
});
