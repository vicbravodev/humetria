<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Seating\Actions\SaveSeat;
use App\Modules\Seating\Http\Controllers\SeatController;
use App\Modules\Seating\Models\Seat;
use App\Modules\Seating\Models\StandingPosition;
use App\Modules\Seating\Support\SeatPhotoStore;
use App\Modules\Telework\Models\CommitteeAssignment;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

mutates(SaveSeat::class, SeatPhotoStore::class, SeatController::class);

beforeEach(function (): void {
    Storage::fake(config()->string('filesystems.seating_files'));
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->center = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte']);
    $this->position = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'name' => 'Cajera']);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function seatForm(array $overrides = []): array
{
    return [
        'kind' => 'ergonomic_chair',
        'use' => 'workstation',
        'quantity' => 2,
        'location' => 'Detrás de la caja 1',
        'position_id' => test()->position->id,
        'backrest' => true, 'height' => true, 'lumbar_support' => true, 'adjustable' => true, 'ergonomic' => true,
        'mobility' => false, 'stability' => true, 'armrests' => false, 'footrest' => false,
        'condition' => 'good',
        'signposted' => false,
        ...$overrides,
    ];
}

function postSeat(array $overrides = [], ?WorkCenter $center = null): Illuminate\Testing\TestResponse
{
    return test()->post(route('tenant.seating.seats.store', [test()->tenant, ($center ?? test()->center)->id]), seatForm($overrides));
}

function jpeg(string $name = 'seat.jpg'): UploadedFile
{
    return UploadedFile::fake()->image($name, 40, 30);
}

it('registers a seat with its nine characteristics, audited and logged with ids only', function (): void {
    Log::spy();
    $this->actingAs($this->hr);

    postSeat()->assertRedirect()->assertSessionHasNoErrors();

    $seat = Seat::query()->sole();
    expect($seat->work_center_id)->toBe($this->center->id)
        ->and($seat->position_id)->toBe($this->position->id)
        ->and($seat->kind->value)->toBe('ergonomic_chair')
        ->and($seat->quantity)->toBe(2)
        ->and([$seat->backrest, $seat->mobility, $seat->armrests])->toBe([true, false, false]);
    $record = ['work_center_id' => $this->center->id, 'seat_id' => $seat->id, 'use' => 'workstation'];
    expect(AuditEntry::query()->where('event', 'seating.seat_registered')->sole()->metadata)->toEqual($record);
    Log::shouldHaveReceived('info')->with('seating.seat_registered', $record)->once();
});

it('requires a designated location for a rest area seat', function (): void {
    $this->actingAs($this->hr);

    postSeat(['use' => 'rest_area', 'location' => '  ', 'position_id' => null])->assertSessionHasErrors('location');
    postSeat(['use' => 'rest_area', 'location' => 'Comedor, planta baja', 'position_id' => null])->assertSessionHasNoErrors();

    expect(Seat::query()->count())->toBe(1)
        ->and(fn () => DB::transaction(fn () => DB::table('seats')->update(['location' => ''])))->toThrow(QueryException::class, 'seats_rest_area_has_location');
});

it('rejects what a seat cannot be', function (array $bad, string $field): void {
    $this->actingAs($this->hr);

    postSeat($bad)->assertSessionHasErrors($field);

    expect(Seat::query()->count())->toBe(0);
})->with([
    'unknown kind' => [['kind' => 'sofa'], 'kind'],
    'unknown use' => [['use' => 'garden'], 'use'],
    'none of them' => [['quantity' => 0], 'quantity'],
    'unknown condition' => [['condition' => 'new'], 'condition'],
    'a characteristic left out' => [['stability' => null], 'stability'],
    'a position of another center' => [['position_id' => (string) Illuminate\Support\Str::uuid()], 'position_id'],
]);

it('refuses a position of another center of the same company', function (): void {
    $other = WorkCenter::factory()->for($this->tenant)->create();
    $foreign = StandingPosition::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $other->id]);
    $this->actingAs($this->hr);

    postSeat(['position_id' => $foreign->id])->assertSessionHasErrors('position_id');
});

it('updates a seat and keeps its photo unless a new one comes', function (): void {
    $this->actingAs($this->hr);
    postSeat(['photo' => jpeg()])->assertSessionHasNoErrors();
    $seat = Seat::query()->sole();
    $path = $seat->photo_path;

    $this->put(route('tenant.seating.seats.update', [$this->tenant, $seat->id]), seatForm(['condition' => 'needs_repair', 'signposted' => true]))->assertRedirect();

    $fresh = Seat::query()->sole();
    expect($fresh->condition->value)->toBe('needs_repair')->and($fresh->signposted)->toBeTrue()->and($fresh->photo_path)->toBe($path);

    $this->put(route('tenant.seating.seats.update', [$this->tenant, $seat->id]), seatForm(['photo' => jpeg('new.jpg')]))->assertRedirect();

    $replaced = Seat::query()->sole()->photo_path;
    expect($replaced)->not->toBe($path);
    Storage::disk(config()->string('filesystems.seating_files'))->assertMissing((string) $path);
    Storage::disk(config()->string('filesystems.seating_files'))->assertExists((string) $replaced);
});

it('recodes a photo with GD and refuses a PDF renamed to .jpg by its content', function (): void {
    $this->actingAs($this->hr);
    $pdf = UploadedFile::fake()->createWithContent('seat.jpg', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

    postSeat(['photo' => $pdf])->assertSessionHasErrors('photo');
    expect(Seat::query()->count())->toBe(0);

    postSeat(['photo' => jpeg()])->assertSessionHasNoErrors();
    $stored = (string) Storage::disk(config()->string('filesystems.seating_files'))->get((string) Seat::query()->sole()->photo_path);
    $info = getimagesizefromstring($stored);

    expect($info)->not->toBeFalse()->and($info[2] ?? null)->toBe(IMAGETYPE_JPEG)->and([$info[0] ?? 0, $info[1] ?? 0])->toBe([40, 30]);
});

it('refuses a photo over the size limit', function (): void {
    config(['seating.max_photo_kb' => 1]);
    $this->actingAs($this->hr);

    postSeat(['photo' => UploadedFile::fake()->image('big.jpg', 600, 600)->size(5)])->assertSessionHasErrors('photo');
});

it('never serves a photo through a public URL: only through an authorized route, never cached', function (): void {
    $this->actingAs($this->hr);
    postSeat(['photo' => jpeg()])->assertSessionHasNoErrors();
    $seat = Seat::query()->sole();

    $this->get(route('tenant.seating.seats.index', [$this->tenant, $this->center->id]))
        ->assertInertia(fn (Assert $page) => $page->where('seats.data.0.photoHref', route('tenant.seating.seats.photo', [$this->tenant, $seat->id]))->missing('seats.data.0.photo_path'));
    $photo = $this->get(route('tenant.seating.seats.photo', [$this->tenant, $seat->id]))->assertOk();
    expect($photo->headers->get('Cache-Control'))->toContain('no-store');
    $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->get(route('tenant.seating.seats.photo', [$this->tenant, $seat->id]))->assertForbidden();
});

it('answers 404 for the photo of a seat without one, of another company and a made-up one', function (): void {
    $seat = Seat::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->actingAs($this->hr);

    $this->get(route('tenant.seating.seats.photo', [$this->tenant, $seat->id]))->assertNotFound();
    $this->get(route('tenant.seating.seats.photo', [$this->tenant, (string) Illuminate\Support\Str::uuid()]))->assertNotFound();
});

it('has no destroy route for seats', function (): void {
    $deletes = collect(Route::getRoutes()->getRoutes())->filter(fn ($route): bool => in_array('DELETE', $route->methods(), true) && str_contains((string) $route->getName(), 'seating.seats'));

    expect($deletes)->toHaveCount(0);
});

it('lets only manage-seating write seats', function (TenantRole $role): void {
    $user = memberOf($this->tenant, $role);
    $seat = Seat::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $this->actingAs($user);

    postSeat()->assertForbidden();
    $this->put(route('tenant.seating.seats.update', [$this->tenant, $seat->id]), seatForm())->assertForbidden();
    expect(Seat::query()->count())->toBe(1);
})->with([TenantRole::Viewer, TenantRole::Committee]);

it('lists the seats of a center, paginated on the server, to who may see the center', function (): void {
    Seat::factory()->count(17)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id]);
    $cora = memberOf($this->tenant, TenantRole::Committee);
    CommitteeAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'user_id' => $cora->id]);
    $other = WorkCenter::factory()->for($this->tenant)->create();

    $this->actingAs($cora)->get(route('tenant.seating.seats.index', [$this->tenant, $this->center->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('seating/seats')->where('seats.total', 17)->where('seats.per_page', 15)->has('seats.data', 15)->where('canManage', false));
    $this->get(route('tenant.seating.seats.index', [$this->tenant, $this->center->id, 'page' => 2]))->assertInertia(fn (Assert $page) => $page->has('seats.data', 2));
    $this->get(route('tenant.seating.seats.index', [$this->tenant, $other->id]))->assertNotFound();
});

it('does not touch the seats of another company', function (): void {
    $foreign = Seat::factory()->create();
    $this->actingAs($this->hr);

    $this->put(route('tenant.seating.seats.update', [$this->tenant, $foreign->id]), seatForm())->assertNotFound();
    $this->get(route('tenant.seating.seats.photo', [$this->tenant, $foreign->id]))->assertNotFound();
    $this->get(route('tenant.seating.seats.index', [$this->tenant, $foreign->work_center_id]))->assertNotFound();
});

it('opens a seat in the form when asked to edit it', function (): void {
    $seat = Seat::factory()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->center->id, 'location' => 'Almacén']);
    $this->actingAs($this->hr)->get(route('tenant.seating.seats.index', [$this->tenant, $this->center->id, 'edit' => $seat->id]))
        ->assertInertia(fn (Assert $page) => $page->where('editing.id', $seat->id)->where('editing.location', 'Almacén')->where('canManage', true));
});
