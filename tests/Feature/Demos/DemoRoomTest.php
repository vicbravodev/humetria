<?php

declare(strict_types=1);

use App\Models\User;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Demos\Contracts\DoorControl;
use App\Modules\Demos\Jobs\PrepareDemo;
use App\Modules\Demos\Jobs\ResetDemo;
use App\Modules\Demos\Models\DemoRun;
use App\Modules\Demos\Support\ClosedDoor;
use App\Modules\Organization\Models\Employee;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Support\TenantContext;
use Carbon\CarbonInterface;
use Database\Factories\Tenancy\WorkCenterFactory;
use Database\Seeders\Demo\Stories\ComplaintsStory;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * La sala de demostración: the console page from which the staff prepares the demo company, enters it as its owner or
 * its HR, and shares it live (the complaint channel, the time-clock screen, the door). Nothing of a person reaches it.
 */

/** A door that remembers what the room asked of it; open or closed as told. */
function fakeDoor(bool $open = false): ArrayObject
{
    $calls = new ArrayObject;
    app()->instance(DoorControl::class, new readonly class($calls, $open) implements DoorControl
    {
        /** @param ArrayObject<int, string> $calls */
        public function __construct(private ArrayObject $calls, private bool $open) {}

        public function open(int $minutes): array
        {
            $this->calls->append("open {$minutes}");

            return ['url' => 'https://humetria.test/demo/CODIGO'];
        }

        public function close(): void
        {
            $this->calls->append('close');
        }

        public function state(): array
        {
            return ['open' => $this->open, 'expires_at' => $this->open ? '2026-11-16T20:30:00+00:00' : null, 'claimed' => 12, 'free' => 28, 'total' => 40];
        }
    });

    return $calls;
}

function demoCompany(): Tenant
{
    return Tenant::factory()->demo()->create(['trade_name' => 'Manufacturas Alba del Bajío', 'slug' => 'alba-demo']);
}

function finishedRun(CarbonInterface $at): DemoRun
{
    return DemoRun::query()->forceCreate(['kind' => 'prepare', 'status' => 'finished', 'started_at' => $at->subMinute(), 'finished_at' => $at]);
}

beforeEach(function (): void {
    $this->door = fakeDoor();
});

it('lives at /admin/demo', function (): void {
    expect(route('demo-room.show', absolute: false))->toBe('/admin/demo')
        ->and(route('demo-room.prepare', absolute: false))->toBe('/admin/demo/prepare')
        ->and(route('demo-room.reset', absolute: false))->toBe('/admin/demo/reset')
        ->and(route('demo-room.door.open', absolute: false))->toBe('/admin/demo/door')
        ->and(route('demo-room.door.close', absolute: false))->toBe('/admin/demo/door')
        ->and(route('demo-room.kiosk.reissue', absolute: false))->toBe('/admin/demo/kiosk');
});

$routes = [
    'the room' => ['get', 'demo-room.show'],
    'prepare' => ['post', 'demo-room.prepare'],
    'reset' => ['post', 'demo-room.reset'],
    'open the door' => ['post', 'demo-room.door.open'],
    'close the door' => ['delete', 'demo-room.door.close'],
    'reissue the screen' => ['post', 'demo-room.kiosk.reissue'],
];

it('sends guests to the login', function (string $method, string $route): void {
    $this->{$method}(route($route))->assertRedirect(route('login'));
})->with($routes);

it('answers 404 to whoever is not on the staff, before anything runs', function (string $method, string $route): void {
    Queue::fake();
    demoCompany();

    $this->actingAs(User::factory()->create())->{$method}(route($route))->assertNotFound()->assertSessionHasNoErrors();

    expect(DemoRun::query()->count())->toBe(0)->and($this->door->getArrayCopy())->toBe([]);
    Queue::assertNothingPushed();
})->with($routes);

it('lets support in as well as the superadmin', function (): void {
    $this->actingAs(support())->get(route('demo-room.show'))->assertOk();
});

it('offers to prepare the demo when there is none', function (): void {
    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('demos/room')
            ->where('company', null)
            ->where('run', null)
            ->where('prepared', null)
            ->where('complaints', null)
            ->where('door', ['open' => false, 'closes_at' => null, 'claimed' => 12, 'free' => 28, 'total' => 40]));
});

it('shows the demo company to enter as its owner or its HR, how old it is and its complaint channel with its QR', function (): void {
    $this->freezeSecond();
    $demo = demoCompany();
    finishedRun(now()->subDay());

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('company', ['id' => $demo->id, 'name' => 'Manufacturas Alba del Bajío'])
            ->where('run.status', 'finished')
            ->where('prepared.ago', 'hace 1 día')
            ->where('prepared.stale', false)
            ->where('complaints.url', route('complaints.public.show', ComplaintsStory::accessCode()))
            ->where('complaints.qr', fn (string $qr): bool => str_starts_with($qr, 'data:image/svg+xml;base64,')));
});

it('warns that the demo goes stale once it is more than two days old', function (int $minutes, bool $stale): void {
    $this->freezeSecond();
    demoCompany();
    finishedRun(now()->subDays(2)->subMinutes($minutes));

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('prepared.stale', $stale));
})->with([
    'exactly two days' => [0, false],
    'two days and a minute' => [1, true],
]);

it('dates the preparation by the last run that finished, not by one running or failed after it', function (): void {
    $this->freezeSecond();
    demoCompany();
    finishedRun(now()->subDays(3));
    DemoRun::query()->forceCreate(['kind' => 'reset', 'status' => 'failed', 'step' => 'NOM-035', 'error_class' => RuntimeException::class, 'started_at' => now()->subHour(), 'finished_at' => now()->subHour()]);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('prepared.ago', 'hace 3 días')
            ->where('run', ['kind' => 'reset', 'status' => 'failed', 'step' => 'NOM-035', 'error_class' => RuntimeException::class]));
});

it('shows the step a running preparation is on', function (): void {
    (DemoRun::start('prepare') ?? throw new LogicException('Not started.'))->step('Asistencia');

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('run', ['kind' => 'prepare', 'status' => 'running', 'step' => 'Asistencia', 'error_class' => null]));
});

it('shows the door open, until when, and how many workers are left for the audience', function (): void {
    fakeDoor(open: true);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(fn (Assert $page) => $page->where('door', ['open' => true, 'closes_at' => '14:30', 'claimed' => 12, 'free' => 28, 'total' => 40]));
});

it('reads the page in a fixed number of queries', function (): void {
    demoCompany();
    finishedRun(now()->subDay());
    $this->actingAs(superadmin());

    $this->expectsDatabaseQueryCount(9);

    $this->get(route('demo-room.show'))->assertOk();
});

it('carries no person of the demo: no answer, no name, no email', function (): void {
    $demo = demoCompany();
    finishedRun(now()->subDay());
    $center = WorkCenterFactory::new()->createOne(['tenant_id' => $demo->id]);
    workIn($demo);
    $worker = Employee::factory()->create(['tenant_id' => $demo->id, 'work_center_id' => $center->id, 'name' => 'Rosaura Ibarra', 'email' => 'rosaura@demo.humetria.test']);

    $this->actingAs(superadmin())
        ->get(route('demo-room.show'))
        ->assertInertia(function (Assert $page) use ($worker): void {
            $props = $page->toArray()['props'];
            expectNoIndividualResponses($props);
            expect(json_encode($props, JSON_THROW_ON_ERROR))->not->toContain('Rosaura')->not->toContain('demo.humetria.test')->not->toContain($worker->id);
        });
});

it('prepares the demo on the queue, once at a time', function (string $route, string $kind, string $job): void {
    Queue::fake();
    $this->actingAs(superadmin());

    $this->post(route($route))->assertRedirect()->assertSessionHas('inertia.flash_data.toast.type', 'success');
    $this->post(route($route))->assertRedirect()->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Ya hay una preparación corriendo: espera a que termine.']);

    $run = DemoRun::query()->sole();
    expect($run->kind)->toBe($kind)->and($run->status)->toBe('running');
    Queue::assertPushed($job, 1);
    Queue::assertPushed($job, fn (PrepareDemo|ResetDemo $pushed): bool => $pushed->runId === $run->id);
})->with([
    'prepare' => ['demo-room.prepare', 'prepare', PrepareDemo::class],
    'reset' => ['demo-room.reset', 'reset', ResetDemo::class],
]);

it('opens the door for two hours and shows its link and QR this once', function (): void {
    $this->actingAs(superadmin())
        ->post(route('demo-room.door.open'))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.doorLink.url', 'https://humetria.test/demo/CODIGO');

    expect(session('inertia.flash_data.doorLink.qr'))->toStartWith('data:image/svg+xml;base64,')
        ->and($this->door->getArrayCopy())->toBe(['open 120']);
});

it('closes the door', function (): void {
    $this->actingAs(superadmin())->delete(route('demo-room.door.close'))->assertRedirect()->assertSessionHas('inertia.flash_data.toast.type', 'success');

    expect($this->door->getArrayCopy())->toBe(['close']);
});

describe('the time-clock screen', function (): void {
    beforeEach(function (): void {
        $this->demo = demoCompany();
        $this->other = Tenant::factory()->create();
        $this->kiosk = KioskDevice::factory()->create(['tenant_id' => $this->demo->id, 'activated_at' => now()]);
        $this->otherKiosk = KioskDevice::factory()->create(['tenant_id' => $this->other->id, 'activated_at' => now()]);
    });

    it('issues a new link for the demo screen, shown once with its QR, and leaves it waiting to be opened', function (): void {
        $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'))->assertRedirect();

        $link = session('inertia.flash_data.demoKioskLink.url');
        $token = str($link)->afterLast('/')->toString();
        expect($link)->toBe(route('attendance.device.kiosk.activate', ['alba-demo', $token]))
            ->and(session('inertia.flash_data.demoKioskLink.qr'))->toStartWith('data:image/svg+xml;base64,');

        $kiosk = KioskDevice::query()->withoutGlobalScopes()->findOrFail($this->kiosk->id);
        expect($kiosk->token_hash)->toBe(KioskDevice::hashToken($token))->and($kiosk->activated_at)->toBeNull();
    });

    it('enters the demo company only to issue it: the console goes on outside it, without the demo mark', function (): void {
        $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'))->assertRedirect();

        expect(Context::has('tenant_id'))->toBeFalse()
            ->and(Context::hasHidden(TenantContext::DEMO))->toBeFalse();
    });

    it('never touches the screen of another company', function (): void {
        $before = $this->otherKiosk->token_hash;

        $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'));

        expect(KioskDevice::query()->withoutGlobalScopes()->findOrFail($this->otherKiosk->id)->token_hash)->toBe($before);
    });

    it('says so when the screen already shows the code', function (): void {
        $this->kiosk->forceFill(['last_seen_at' => now()])->save();

        $this->actingAs(superadmin())
            ->post(route('demo-room.kiosk.reissue'))
            ->assertSessionMissing('inertia.flash_data.demoKioskLink')
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');
    });
});

it('answers 404 to a new screen link when there is no demo', function (): void {
    $this->actingAs(superadmin())->post(route('demo-room.kiosk.reissue'))->assertNotFound();
});

it('keeps the door closed, with nobody reserved, until the door itself is built', function (): void {
    $door = new ClosedDoor;
    $door->close();

    expect($door->state())->toBe(['open' => false, 'expires_at' => null, 'claimed' => 0, 'free' => 0, 'total' => 0])
        ->and(fn (): array => $door->open(120))->toThrow(LogicException::class);
});
