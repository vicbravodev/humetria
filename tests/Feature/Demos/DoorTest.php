<?php

declare(strict_types=1);

use App\Modules\Demos\Actions\AdmitVisitor;
use App\Modules\Demos\Actions\CloseDoor;
use App\Modules\Demos\Actions\OpenDoor;
use App\Modules\Demos\Actions\ReadDoorState;
use App\Modules\Demos\Contracts\DemoDoorPool;
use App\Modules\Demos\Models\DoorAssignment;
use App\Modules\Demos\Models\DoorSession;
use App\Modules\Organization\Models\Employee;
use App\Modules\Telework\Contracts\PortalAccess;
use App\Modules\Tenancy\Models\Tenant;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

mutates(OpenDoor::class, CloseDoor::class, AdmitVisitor::class, ReadDoorState::class);

const DOOR_COOKIE = 'demo_door';

/**
 * A demo company with `$people` door people, and a pool that hands them out in that order: only what the door needs, so no
 * test pays for preparing the whole demo (tests/Feature/Demo/DoorPoolTest.php proves the real pool once).
 *
 * @param  list<string>  $extra  ids the pool offers before the demo's own people
 * @return list<string> the demo people's ids, in the pool's order
 */
function demoDoorFixture(object $test, int $people = 3, array $extra = []): array
{
    $test->demo = Tenant::factory()->demo()->create(['slug' => 'alba']);
    $ids = Employee::factory()->count($people)->create(['tenant_id' => $test->demo->id])->pluck('id')->all();
    usePool([...$extra, ...$ids]);

    return $ids;
}

/** @param  list<string>  $ids */
function usePool(array $ids): void
{
    app()->instance(DemoDoorPool::class, new readonly class($ids) implements DemoDoorPool
    {
        /** @param  list<string>  $ids */
        public function __construct(private array $ids) {}

        public function employeeIds(): array
        {
            return $this->ids;
        }
    });
}

function openDoorPath(int $minutes = 120): string
{
    return (string) parse_url(resolve(OpenDoor::class)->handle($minutes)['url'], PHP_URL_PATH);
}

function portalOf(Tenant $tenant, string $employeeId): string
{
    workIn($tenant);

    return (string) resolve(PortalAccess::class)->url($employeeId);
}

function scan(object $test, string $path, string $address = '203.0.113.7'): TestResponse
{
    return $test->withServerVariables(['REMOTE_ADDR' => $address])->get($path);
}

function assertNeutralDoor(TestResponse $response, string $state): void
{
    $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('demos/door')->where('state', $state)->etc());
}

it('sends a visitor to the portal of a free demo worker, with a signed cookie that remembers it', function (): void {
    [$first] = demoDoorFixture($this);
    $path = openDoorPath();

    $response = scan($this, $path)->assertRedirect(portalOf($this->demo, $first));

    $assignment = DoorAssignment::query()->sole();
    $cookie = $response->getCookie(DOOR_COOKIE, false);
    expect($assignment->employee_id)->toBe($first)
        ->and($cookie?->isHttpOnly())->toBeTrue()
        ->and($cookie?->getSameSite())->toBe('lax')
        // Encrypted and authenticated by the app key: the plain assignment never travels.
        ->and($cookie?->getValue())->not->toContain($assignment->id)
        ->and($response->getCookie(DOOR_COOKIE)?->getValue())->toBe($assignment->id);
});

it('gives a returning visitor the same worker, and never a second one', function (): void {
    [$first] = demoDoorFixture($this);
    $path = openDoorPath();
    $assignment = scan($this, $path)->getCookie(DOOR_COOKIE)?->getValue();

    foreach (range(1, 3) as $ignored) {
        $this->withCookie(DOOR_COOKIE, (string) $assignment);
        scan($this, $path)->assertRedirect(portalOf($this->demo, $first));
    }

    expect(DoorAssignment::query()->count())->toBe(1);
});

it('gives the next visitor the next free worker', function (): void {
    [$first, $second] = demoDoorFixture($this);
    $path = openDoorPath();

    scan($this, $path)->assertRedirect(portalOf($this->demo, $first));
    scan($this, $path)->assertRedirect(portalOf($this->demo, $second));
});

it('says the room is full when no worker is free', function (): void {
    demoDoorFixture($this, 40);
    $path = openDoorPath();
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message;
    });

    foreach (range(1, 40) as $ignored) {
        scan($this, $path)->assertRedirect();
    }

    assertNeutralDoor(scan($this, $path), 'full');
    expect(DoorAssignment::query()->distinct()->count('employee_id'))->toBe(40)
        ->and($logged)->toContain('demo.door_full')
        ->and(array_count_values($logged)['demo.door_admitted'])->toBe(40);
});

it('keeps a worker handed out in an earlier session taken, so whoever answered is never handed out again', function (): void {
    [$first, $second] = demoDoorFixture($this, 2);
    scan($this, openDoorPath())->assertRedirect(portalOf($this->demo, $first));

    scan($this, openDoorPath())->assertRedirect(portalOf($this->demo, $second));
});

it('refuses a closed, expired, replaced or unknown code with the same page', function (): void {
    demoDoorFixture($this);
    $closed = openDoorPath();
    resolve(CloseDoor::class)->handle();
    $replaced = openDoorPath();
    $expired = openDoorPath(120);
    $this->travel(121)->minutes();

    $pages = array_map(function (string $path): array {
        $response = scan($this, $path);

        $page = $response->viewData('page');

        // Everything but the address the visitor typed, which the page echoes back.
        return ['status' => $response->status(), 'page' => array_diff_key($page, ['url' => true])];
    }, [$closed, $replaced, $expired, '/demo/'.str_repeat('A', 24), '/demo/nope', '/demo/'.str_repeat('é', 30)]);

    expect(array_unique(array_map(serialize(...), $pages)))->toHaveCount(1)
        ->and($pages[0]['status'])->toBe(200)
        ->and($pages[0]['page']['component'])->toBe('demos/door')
        ->and($pages[0]['page']['props']['state'])->toBe('closed')
        ->and(DoorAssignment::query()->count())->toBe(0);
});

it('answers closed when there is no demo company', function (): void {
    $tenant = Tenant::factory()->create();
    $code = 'abcdefghijklmnopqrstuvwx';
    DoorSession::query()->withoutGlobalScopes()->forceCreate(['tenant_id' => $tenant->id, 'code_hash' => hash('sha256', $code), 'expires_at' => now()->addHour()]);
    usePool(Employee::factory()->count(1)->create(['tenant_id' => $tenant->id])->pluck('id')->all());

    assertNeutralDoor(scan($this, '/demo/'.$code), 'closed');
    expect(DoorAssignment::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('only ever hands out workers of the demo company', function (): void {
    $other = Tenant::factory()->create();
    $stranger = Employee::factory()->create(['tenant_id' => $other->id]);
    $gone = Employee::factory()->create(['tenant_id' => $other->id]);
    [$first] = demoDoorFixture($this, 1, [$stranger->id, $gone->id, '6b1c4f0e-1111-4c1d-9e3a-000000000000', 'not-a-uuid']);

    // A session of another company, with a code someone knows, opens nothing.
    $code = 'zyxwvutsrqponmlkjihgfedc';
    DoorSession::query()->withoutGlobalScopes()->forceCreate(['tenant_id' => $other->id, 'code_hash' => hash('sha256', $code), 'expires_at' => now()->addHour()]);
    assertNeutralDoor(scan($this, '/demo/'.$code), 'closed');

    $path = openDoorPath();
    scan($this, $path)->assertRedirect(portalOf($this->demo, $first));
    assertNeutralDoor(scan($this, $path), 'full');

    expect(DoorAssignment::query()->withoutGlobalScopes()->pluck('employee_id')->all())->toBe([$first]);
});

it('throttles by address', function (): void {
    demoDoorFixture($this);
    $path = '/demo/'.str_repeat('B', 24);

    foreach (range(1, 120) as $ignored) {
        scan($this, $path, '198.51.100.1')->assertOk();
    }

    scan($this, $path, '198.51.100.1')->assertTooManyRequests();
    scan($this, $path, '198.51.100.2')->assertOk();
});

it('never hands a worker to a second visitor until a reset, whatever the door does', function (): void {
    demoDoorFixture($this, 3);

    // A session, the same door closed and opened again, a new code that replaces it: one worker each, never one twice.
    scan($this, openDoorPath())->assertRedirect();
    resolve(CloseDoor::class)->handle();
    scan($this, openDoorPath())->assertRedirect();
    $replaced = openDoorPath();
    scan($this, $replaced)->assertRedirect();
    $this->travel(121)->minutes();
    assertNeutralDoor(scan($this, $replaced), 'closed');

    expect(DoorAssignment::query()->count())->toBe(3)
        ->and(DoorAssignment::query()->distinct()->count('employee_id'))->toBe(3);
});

it('says the room is full in a new session once every worker was handed out', function (): void {
    demoDoorFixture($this, 2);
    $first = openDoorPath();
    scan($this, $first)->assertRedirect();
    scan($this, $first)->assertRedirect();
    resolve(CloseDoor::class)->handle();

    assertNeutralDoor(scan($this, openDoorPath()), 'full');
    expect(DoorAssignment::query()->count())->toBe(2);
});

it('tells no cache to keep the redirect that carries the portal', function (): void {
    demoDoorFixture($this);

    expect(scan($this, openDoorPath())->assertRedirect()->headers->get('Cache-Control'))->toContain('no-store');
});

it('keeps the cookie no longer than the session that let the visitor in', function (): void {
    $this->freezeTime();
    demoDoorFixture($this);
    $path = openDoorPath(90);
    $this->travel(30)->minutes();

    $cookie = scan($this, $path)->getCookie(DOOR_COOKIE, false);

    expect($cookie?->getExpiresTime())->toBeGreaterThan(now()->getTimestamp())
        ->toBeLessThanOrEqual(now()->addMinutes(60)->getTimestamp());
});

it('tells the room how many workers are taken and free', function (): void {
    $this->freezeTime();
    demoDoorFixture($this, 3);
    expect(resolve(ReadDoorState::class)->handle()->toArray())->toMatchArray(['open' => false, 'expires_at' => null, 'claimed' => 0, 'free' => 3, 'total' => 3]);

    $path = openDoorPath(90);
    scan($this, $path);

    expect(resolve(ReadDoorState::class)->handle()->toArray())->toBe(['open' => true, 'expires_at' => now()->addMinutes(90)->toIso8601String(), 'claimed' => 1, 'free' => 2, 'total' => 3]);

    resolve(CloseDoor::class)->handle();
    expect(resolve(ReadDoorState::class)->handle()->open)->toBeFalse();
});

it('tells the room nothing when there is no demo company', function (): void {
    usePool(['6b1c4f0e-1111-4c1d-9e3a-000000000000']);

    expect(resolve(ReadDoorState::class)->handle()->toArray())->toBe(['open' => false, 'expires_at' => null, 'claimed' => 0, 'free' => 0, 'total' => 0]);
});

it('cannot open a door without a demo company', function (): void {
    resolve(OpenDoor::class)->handle();
})->throws(LogicException::class);

it('keeps only the hash of the code, a random one each time, and logs neither', function (): void {
    demoDoorFixture($this);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.' '.json_encode($log->context);
    });

    $code = basename(openDoorPath());
    $another = basename(openDoorPath());
    resolve(CloseDoor::class)->handle();

    expect($code)->toMatch('/^[A-Za-z0-9]{24}$/')
        ->and($another)->not->toBe($code)
        ->and(DoorSession::query()->pluck('code_hash')->all())->toEqualCanonicalizing([hash('sha256', $code), hash('sha256', $another)])
        ->and(DoorSession::query()->whereNull('closed_at')->count())->toBe(0)
        ->and(implode("\n", $logged))->not->toContain($code)->not->toContain($another)
        ->and(array_map(fn (string $line): string => strtok($line, ' '), $logged))->toBe(['demo.door_opened', 'demo.door_closed', 'demo.door_opened', 'demo.door_closed']);
});

it('links no visitor to any answer', function (): void {
    [$first] = demoDoorFixture($this);
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $log) use (&$logged): void {
        $logged[] = $log->message.' '.json_encode($log->context);
    });
    $anonymous = fn (): array => [DB::table('anonymous_responses')->count(), DB::table('engagement_responses')->count()];
    $before = $anonymous();

    $portal = portalOf($this->demo, $first);
    scan($this, openDoorPath());
    $token = basename($portal);
    // The door's own lines (the portal logs its link being issued, as it does for every person, without the token).
    $doorLines = array_values(array_filter($logged, fn (string $line): bool => str_starts_with($line, 'demo.door')));

    expect(Schema::getColumnListing('demo_door_assignments'))->toEqualCanonicalizing(['id', 'tenant_id', 'session_id', 'employee_id', 'claimed_at'])
        ->and(Schema::getColumnListing('demo_door_sessions'))->toEqualCanonicalizing(['id', 'tenant_id', 'code_hash', 'expires_at', 'closed_at', 'created_at', 'updated_at'])
        ->and($anonymous())->toBe($before)
        ->and($doorLines)->toHaveCount(2)
        ->and(implode("\n", $doorLines))->not->toContain($token)->not->toContain($first)
        ->and($doorLines[1])->toContain(DoorAssignment::query()->sole()->id);
});

it('treats a tampered cookie as a first visit, and never as someone else\'s worker', function (): void {
    [, $second, $third] = demoDoorFixture($this);
    $path = openDoorPath();
    $theirs = scan($this, $path)->getCookie(DOOR_COOKIE)?->getValue();

    $this->withUnencryptedCookie(DOOR_COOKIE, (string) $theirs);
    scan($this, $path)->assertRedirect(portalOf($this->demo, $second));

    $this->withUnencryptedCookie(DOOR_COOKIE, 'garbage');
    scan($this, $path)->assertRedirect(portalOf($this->demo, $third));
});

it('ignores a cookie of an earlier session', function (): void {
    [, $second] = demoDoorFixture($this);
    $old = scan($this, openDoorPath())->getCookie(DOOR_COOKIE)?->getValue();

    $this->withCookie(DOOR_COOKIE, (string) $old);
    scan($this, openDoorPath())->assertRedirect(portalOf($this->demo, $second));
});

it('leaks nothing in the headers of the door', function (): void {
    demoDoorFixture($this);
    $path = openDoorPath();
    $code = basename($path);

    $redirect = scan($this, $path);
    $refused = scan($this, '/demo/'.str_repeat('C', 24));

    foreach ([$redirect, $refused] as $response) {
        expect($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
            ->and($response->headers->get('Content-Security-Policy'))->toContain("'nonce-");
        $headers = collect($response->headers->allPreserveCase())->except(['Set-Cookie'])->flatten()->implode("\n");
        expect($headers)->not->toContain($code);
    }

    expect($redirect->headers->get('Location'))->toStartWith(url('/portal/'));
});
