<?php

declare(strict_types=1);

use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Contracts\DoorControl;
use App\Modules\Demos\Support\DoorKeeper;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantContext;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

/*
 * The room drives the real door: «Abrir sala» hands out a code that lets the audience in, «Cerrar sala» shuts it.
 */

it('drives the real door, never a stand-in', function (): void {
    expect(resolve(DoorControl::class))->toBeInstanceOf(DoorKeeper::class);
});

it('opens the door from the room, lets a visitor in with its code, and closes it again', function (): void {
    $demo = Tenant::factory()->demo()->create(['slug' => 'alba']);
    $ids = Employee::factory()->count(3)->create(['tenant_id' => $demo->id])->pluck('id')->all();
    app()->instance(DemoDoorPool::class, new readonly class($ids) implements DemoDoorPool
    {
        /** @param  list<string>  $ids */
        public function __construct(private array $ids) {}

        public function employeeIds(): array
        {
            return $this->ids;
        }
    });
    $admin = superadmin();

    $this->actingAs($admin)->post(route('demo-room.door.open'))->assertRedirect();
    $path = (string) parse_url((string) session('inertia.flash_data.doorLink.url'), PHP_URL_PATH);
    expect($path)->toMatch('#^/demo/[A-Za-z0-9]{24}$#');

    $this->actingAs($admin)->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('door.open', true)->where('door.total', 3)->where('door.free', 3)->where('door.claimed', 0));

    auth()->logout();
    $this->get($path)->assertRedirect()->assertRedirectContains('/portal');

    $this->actingAs($admin)->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('door.open', true)->where('door.free', 2)->where('door.claimed', 1));

    $this->actingAs($admin)->delete(route('demo-room.door.close'))->assertRedirect();
    auth()->logout();

    $this->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component('demos/door')->where('state', 'closed'));
});

it('works the door inside the demo company only: the room itself goes on outside it, without the demo mark', function (string $method, string $route): void {
    Tenant::factory()->demo()->create(['slug' => 'alba']);
    $admin = superadmin();

    $response = $this->actingAs($admin)->{$method}(route($route));

    expect(Context::has('tenant_id'))->toBeFalse()
        ->and(Context::hasHidden(TenantContext::DEMO))->toBeFalse()
        ->and(resolve(PermissionRegistrar::class)->getPermissionsTeamId())->toBeNull();

    if ($method === 'get') {
        $response->assertInertia(fn (Assert $page) => $page->component('demos/room')->where('demo', false));
    }
})->with([
    'the room' => ['get', 'demo-room.show'],
    'opening' => ['post', 'demo-room.door.open'],
    'closing' => ['delete', 'demo-room.door.close'],
]);

it('records in the demo company\'s audit trail who opened and closed the door, with ids only', function (): void {
    $demo = Tenant::factory()->demo()->create(['slug' => 'alba']);
    $admin = superadmin();

    $this->actingAs($admin)->post(route('demo-room.door.open'))->assertRedirect();
    $this->actingAs($admin)->delete(route('demo-room.door.close'))->assertRedirect();

    $entries = DB::table('audit_entries')->whereIn('event', ['demo.door_opened', 'demo.door_closed'])->orderBy('id')->get();
    $session = DB::table('demo_door_sessions')->sole();
    expect($entries->pluck('event')->all())->toEqualCanonicalizing(['demo.door_opened', 'demo.door_closed'])
        ->and($entries->pluck('tenant_id')->unique()->all())->toBe([$demo->id])
        ->and($entries->pluck('actor_id')->unique()->all())->toBe([$admin->id])
        ->and($entries->pluck('subject_id')->unique()->all())->toBe([$session->id])
        ->and($entries->pluck('metadata')->implode(' '))->not->toContain('/demo/');
});
