<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\AuditEntry;
use App\Modules\Attendance\Http\Controllers\AttendanceController;
use App\Modules\Attendance\Http\Controllers\KioskController;
use App\Modules\Attendance\Http\Controllers\ZoneController;
use App\Modules\Attendance\Http\Requests\ZoneRequest;
use App\Modules\Attendance\Models\AttendanceZone;
use App\Modules\Attendance\Models\KioskDevice;
use App\Modules\Tenancy\Enums\TenantRole;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\Tenancy\Models\WorkCenter;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;

mutates(AttendanceController::class, ZoneController::class, ZoneRequest::class, KioskController::class);

beforeEach(function (): void {
    $this->tenant = Tenant::factory()->create(['slug' => 'acme']);
    workIn($this->tenant);
    $this->plant = WorkCenter::factory()->for($this->tenant)->create(['name' => 'Planta Norte', 'main_activity' => 'Manufactura']);
    EmployeeFactory::new()->count(2)->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    EmployeeFactory::new()->deactivated()->create(['tenant_id' => $this->tenant->id, 'work_center_id' => $this->plant->id]);
    $this->hr = memberOf($this->tenant, TenantRole::Hr);
});

function attendanceCenterUrl(WorkCenter $center): string
{
    return route('tenant.attendance.center', ['acme', $center->id]);
}

function zoneOf(WorkCenter $center, array $attributes = []): AttendanceZone
{
    return AttendanceZone::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, 'name' => 'Nave 1', 'latitude' => 19.432712, 'longitude' => -99.133298, 'radius_m' => 80, ...$attributes]);
}

/** The headers of a visit the app makes inside a page already loaded, on the current assets. */
function inertiaVisit(): array
{
    return ['X-Inertia' => 'true', 'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request())];
}

/** A zone as the form sends it. */
function zoneInput(array $overrides = []): array
{
    return ['name' => 'Patio de carga', 'latitude' => '19.4401', 'longitude' => '-99.2011', 'radius_m' => '120', ...$overrides];
}

describe('the center', function (): void {
    it('shows RH its zones with where each is, and its active people', function (): void {
        $zone = zoneOf($this->plant);
        $this->actingAs($this->hr);

        $this->get(attendanceCenterUrl($this->plant))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance/center')
            ->where('center', ['id' => $this->plant->id, 'name' => 'Planta Norte', 'mainActivity' => 'Manufactura'])
            ->where('people', 2)
            ->where('canManage', true)
            ->where('zones.total', 1)
            ->where('zones.per_page', 25)
            ->where('zones.data.0', ['id' => $zone->id, 'name' => 'Nave 1', 'radius' => 80, 'latitude' => 19.432712, 'longitude' => -99.133298]));
    });

    it('shows a viewer the zones by name and radius only, never where they are', function (): void {
        zoneOf($this->plant);
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $response = $this->get(attendanceCenterUrl($this->plant))->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
            ->where('canManage', false)
            ->where('zones.data.0', fn ($zone): bool => collect($zone)->keys()->all() === ['id', 'name', 'radius']));

        expect($response->getContent())->not->toContain('19.4327')->not->toContain('99.1332')->not->toContain('latitude');
    });

    it('lets its own document read the position, for «Usar mi ubicación actual», the rest of the panel still not', function (): void {
        $this->actingAs($this->hr);

        $this->get(attendanceCenterUrl($this->plant))->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=(), usb=()');
        $this->get(route('tenant.attendance.index', 'acme'))->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    });

    it('is loaded as a whole document when opened from another page, since a page opened inside the panel keeps its document', function (): void {
        $this->actingAs($this->hr);

        $this->get(attendanceCenterUrl($this->plant), [...inertiaVisit(), 'Referer' => route('tenant.attendance.index', 'acme')])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', attendanceCenterUrl($this->plant));
        $this->get(attendanceCenterUrl($this->plant), inertiaVisit())->assertStatus(409);
    });

    it('stays an Inertia visit within its own document, so what a save flashed reaches it once', function (): void {
        $this->actingAs($this->hr);
        $this->from(attendanceCenterUrl($this->plant))->post(route('tenant.attendance.zones.store', ['acme', $this->plant->id]), zoneInput());
        $within = [...inertiaVisit(), 'Referer' => attendanceCenterUrl($this->plant).'?zones=1'];

        $this->get(attendanceCenterUrl($this->plant), $within)->assertOk()->assertJsonPath('component', 'attendance/center')->assertJsonPath('flash.toast.message', 'Zona guardada.');
        $this->get(attendanceCenterUrl($this->plant), $within)->assertOk()->assertJsonMissingPath('flash.toast');
    });

    it('shows a center without zones as such', function (): void {
        $this->actingAs($this->hr);

        $this->get(attendanceCenterUrl($this->plant))->assertInertia(fn (AssertableInertia $page) => $page->where('zones.total', 0));
    });

    it('pages the zones 25 at a time, by name', function (): void {
        foreach (range(1, 26) as $number) {
            zoneOf($this->plant, ['name' => sprintf('Zona %02d', $number)]);
        }
        $this->actingAs($this->hr);

        $this->get(attendanceCenterUrl($this->plant).'?zones=2')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('zones.total', 26)->has('zones.data', 1)->where('zones.data.0.name', 'Zona 26'));
    });

    it('answers 404 to a center archived, of another company or unknown', function (): void {
        $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
        $foreign = WorkCenter::factory()->for(Tenant::factory()->create())->create();
        $this->actingAs($this->hr);

        $this->get(attendanceCenterUrl($archived))->assertNotFound();
        $this->get(attendanceCenterUrl($foreign))->assertNotFound();
        $this->get(route('tenant.attendance.center', ['acme', '0b8a3a4e-0000-4000-8000-000000000000']))->assertNotFound();
    });

    it('keeps the committee out and answers 404 to a union', function (): void {
        $this->actingAs(memberOf($this->tenant, TenantRole::Committee));
        $this->get(attendanceCenterUrl($this->plant))->assertForbidden();

        $union = Tenant::factory()->union()->create();
        $this->actingAs(memberOf($union, TenantRole::Owner));
        $this->get(route('tenant.attendance.center', [$union->slug, $this->plant->id]))->assertNotFound();
    });
});

describe('a zone', function (): void {
    it('is added by RH to the center, to the audit trail and the log with ids only', function (): void {
        Log::spy();
        $this->actingAs($this->hr);

        $this->from(attendanceCenterUrl($this->plant))->post(route('tenant.attendance.zones.store', ['acme', $this->plant->id]), zoneInput())
            ->assertRedirect(attendanceCenterUrl($this->plant))
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'success', 'message' => 'Zona guardada.']);

        $zone = AttendanceZone::query()->sole();
        expect($zone)->work_center_id->toBe($this->plant->id)->name->toBe('Patio de carga')->latitude->toBe(19.4401)->longitude->toBe(-99.2011)->radius_m->toBe(120);
        $entry = AuditEntry::query()->where('event', 'attendance.zone_saved')->sole();
        expect($entry)->subject_id->toBe($zone->id)->metadata->toBe(['work_center_id' => $this->plant->id])
            ->and(json_encode($entry->toArray()))->not->toContain('19.44')->not->toContain('99.20');
        Log::shouldHaveReceived('info')->with('attendance.zone_saved', ['zone_id' => $zone->id, 'work_center_id' => $this->plant->id]);
    });

    it('is changed by RH in its own center', function (): void {
        $zone = zoneOf($this->plant);
        $this->actingAs($this->hr);

        $this->from(attendanceCenterUrl($this->plant))->put(route('tenant.attendance.zones.update', ['acme', $this->plant->id, $zone->id]), zoneInput(['name' => 'Nave 1 y patio', 'radius_m' => '150']))
            ->assertRedirect(attendanceCenterUrl($this->plant));

        expect($zone->fresh())->name->toBe('Nave 1 y patio')->radius_m->toBe(150)->work_center_id->toBe($this->plant->id);
    });

    it('is checked as the database checks it, in Spanish', function (array $input, string $field, string $message): void {
        $this->actingAs($this->hr);

        $this->from(attendanceCenterUrl($this->plant))->post(route('tenant.attendance.zones.store', ['acme', $this->plant->id]), zoneInput($input))
            ->assertSessionHasErrors([$field => $message]);
        expect(AttendanceZone::query()->count())->toBe(0);
    })->with([
        'no name' => [['name' => ' '], 'name', 'El campo nombre es obligatorio.'],
        'a long name' => [['name' => str_repeat('a', 61)], 'name', 'El campo nombre no debe tener más de 60 caracteres.'],
        'a latitude past the pole' => [['latitude' => '90.5'], 'latitude', 'El campo latitud debe estar entre -90 y 90.'],
        'a longitude past the antimeridian' => [['longitude' => '-180.1'], 'longitude', 'El campo longitud debe estar entre -180 y 180.'],
        'no latitude' => [['latitude' => ''], 'latitude', 'El campo latitud es obligatorio.'],
        'a radius under 10 m' => [['radius_m' => '9'], 'radius_m', 'El campo radio debe estar entre 10 y 5000.'],
        'a radius over 5 km' => [['radius_m' => '5001'], 'radius_m', 'El campo radio debe estar entre 10 y 5000.'],
        'a radius with decimals' => [['radius_m' => '20.5'], 'radius_m', 'El campo radio debe ser un número entero.'],
    ]);

    it('takes the exact limits of the database', function (): void {
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.zones.store', ['acme', $this->plant->id]), zoneInput(['latitude' => '-90', 'longitude' => '180', 'radius_m' => '10']))->assertSessionHasNoErrors();
        $this->post(route('tenant.attendance.zones.store', ['acme', $this->plant->id]), zoneInput(['latitude' => '90', 'longitude' => '-180', 'radius_m' => '5000']))->assertSessionHasNoErrors();

        expect(AttendanceZone::query()->count())->toBe(2);
    });

    it('cannot be added or changed by a viewer', function (): void {
        $zone = zoneOf($this->plant);
        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer));

        $this->post(route('tenant.attendance.zones.store', ['acme', $this->plant->id]), zoneInput())->assertForbidden();
        $this->put(route('tenant.attendance.zones.update', ['acme', $this->plant->id, $zone->id]), zoneInput())->assertForbidden();

        expect(AttendanceZone::query()->sole()->name)->toBe('Nave 1');
    });

    it('answers 404 for a center archived or of another company, and for a zone of another center or company', function (): void {
        $archived = WorkCenter::factory()->for($this->tenant)->archived()->create();
        $office = WorkCenter::factory()->for($this->tenant)->create();
        $officeZone = zoneOf($office);
        $globex = Tenant::factory()->create();
        $theirCenter = WorkCenter::factory()->for($globex)->create();
        $theirZone = AttendanceZone::factory()->create(['tenant_id' => $globex->id, 'work_center_id' => $theirCenter->id]);
        workIn($this->tenant);
        $this->actingAs($this->hr);

        $this->post(route('tenant.attendance.zones.store', ['acme', $archived->id]), zoneInput())->assertNotFound();
        $this->post(route('tenant.attendance.zones.store', ['acme', $theirCenter->id]), zoneInput())->assertNotFound();
        $this->put(route('tenant.attendance.zones.update', ['acme', $this->plant->id, $officeZone->id]), zoneInput())->assertNotFound();
        $this->put(route('tenant.attendance.zones.update', ['acme', $this->plant->id, $theirZone->id]), zoneInput())->assertNotFound();
        $this->put(route('tenant.attendance.zones.update', ['acme', $theirCenter->id, $theirZone->id]), zoneInput())->assertNotFound();

        expect(AttendanceZone::query()->where('name', 'Patio de carga')->count())->toBe(0)
            ->and($officeZone->fresh()?->work_center_id)->toBe($office->id);
    });
});

describe('the screens of the QR', function (): void {
    beforeEach(function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-11-11 18:00:00', 'UTC'));
    });

    function screenOf(WorkCenter $center, array $attributes = []): KioskDevice
    {
        return KioskDevice::factory()->create(['tenant_id' => $center->tenant_id, 'work_center_id' => $center->id, ...$attributes]);
    }

    it('lists the center\'s screens, the ones in use first, with their state and when they were last seen, never their token', function (): void {
        $revoked = screenOf($this->plant, ['activated_at' => now()->subDays(9), 'revoked_at' => now()->subDays(2), 'created_at' => now()->subDays(10)]);
        $active = screenOf($this->plant, ['activated_at' => now()->subDays(5), 'last_seen_at' => now()->subMinutes(3), 'created_at' => now()->subDays(5)]);
        $issued = screenOf($this->plant, ['created_at' => now()->subHour()]);
        screenOf(WorkCenter::factory()->for($this->tenant)->create());
        $this->actingAs($this->hr);

        $response = $this->get(attendanceCenterUrl($this->plant))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('kiosks.total', 3)
            ->where('kiosks.per_page', 25)
            ->where('kiosks.data', [
                ['id' => $issued->id, 'state' => 'issued', 'issuedAt' => '2026-11-11 11:00', 'lastSeenAt' => null, 'revokedAt' => null],
                ['id' => $active->id, 'state' => 'active', 'issuedAt' => '2026-11-06 12:00', 'lastSeenAt' => '2026-11-11 11:57', 'revokedAt' => null],
                ['id' => $revoked->id, 'state' => 'revoked', 'issuedAt' => '2026-11-01 12:00', 'lastSeenAt' => null, 'revokedAt' => '2026-11-09 12:00'],
            ]));

        expect($response->getContent())->not->toContain($active->token_hash)->not->toContain('token');
    });

    it('counts a screen opened but never seen as issued: a link preview may have opened it', function (): void {
        $opened = screenOf($this->plant, ['activated_at' => now()]);
        $this->actingAs($this->hr);

        $this->get(attendanceCenterUrl($this->plant))->assertInertia(fn (AssertableInertia $page) => $page->where('kiosks.data.0', ['id' => $opened->id, 'state' => 'issued', 'issuedAt' => '2026-11-11 12:00', 'lastSeenAt' => null, 'revokedAt' => null]));
    });

    it('shows RH the link of a screen just issued once, gone on the next visit', function (): void {
        $this->actingAs($this->hr);
        $within = [...inertiaVisit(), 'Referer' => attendanceCenterUrl($this->plant)];

        $this->from(attendanceCenterUrl($this->plant))->post(route('tenant.attendance.kiosks.store', 'acme'), ['work_center_id' => $this->plant->id])->assertRedirect(attendanceCenterUrl($this->plant));
        $link = $this->get(attendanceCenterUrl($this->plant), $within)->assertOk()->json('flash.kioskLink');

        expect($link)->toStartWith(url('a/acme/kiosk/'));
        $this->get(attendanceCenterUrl($this->plant), $within)->assertOk()->assertJsonMissingPath('flash.kioskLink');
        $this->get(attendanceCenterUrl($this->plant))->assertInertia(fn (AssertableInertia $page) => $page->missingFlash('kioskLink'));
    });

    it('lets RH issue the link of a screen never seen again, showing the new one once', function (): void {
        $issued = screenOf($this->plant);
        $this->actingAs($this->hr);

        $this->from(attendanceCenterUrl($this->plant))->post(route('tenant.attendance.kiosks.reissue', ['acme', $issued->id]))
            ->assertRedirect(attendanceCenterUrl($this->plant))
            ->assertSessionHas('inertia.flash_data.kioskLink');

        expect($issued->fresh()?->token_hash)->not->toBe($issued->token_hash)
            ->and(session('inertia.flash_data.kioskLink'))->toBe(route('attendance.device.kiosk.activate', ['acme', str(session('inertia.flash_data.kioskLink'))->afterLast('/')->toString()]));
    });

    it('tells RH to revoke a screen in use instead of issuing its link again', function (): void {
        $active = screenOf($this->plant, ['activated_at' => now(), 'last_seen_at' => now()]);
        $this->actingAs($this->hr);

        $this->from(attendanceCenterUrl($this->plant))->post(route('tenant.attendance.kiosks.reissue', ['acme', $active->id]))
            ->assertRedirect(attendanceCenterUrl($this->plant))
            ->assertSessionMissing('inertia.flash_data.kioskLink')
            ->assertSessionHas('inertia.flash_data.toast', ['type' => 'error', 'message' => 'Esa pantalla ya muestra el código o está revocada. Para cambiarla, revócala y emite otra.']);

        expect($active->fresh()?->token_hash)->toBe($active->token_hash);
    });

    it('does not let a viewer issue again, and answers 404 for a screen of another company', function (): void {
        $issued = screenOf($this->plant);
        $globex = Tenant::factory()->create();
        $theirs = KioskDevice::factory()->create(['tenant_id' => $globex->id]);
        workIn($this->tenant);

        $this->actingAs(memberOf($this->tenant, TenantRole::Viewer))->post(route('tenant.attendance.kiosks.reissue', ['acme', $issued->id]))->assertForbidden();
        $this->actingAs($this->hr)->post(route('tenant.attendance.kiosks.reissue', ['acme', $theirs->id]))->assertNotFound();

        expect($issued->fresh()?->token_hash)->toBe($issued->token_hash)
            ->and($theirs->fresh()?->token_hash)->toBe($theirs->token_hash);
    });
});
