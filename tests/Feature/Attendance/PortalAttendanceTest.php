<?php

declare(strict_types=1);

use App\Modules\Attendance\Http\Controllers\PortalController;
use App\Modules\Attendance\Support\AttendancePortalSection;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use App\Modules\WorkerAccess\Models\WorkerCredential;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalController::class, AttendancePortalSection::class);

/*
 * «Mi jornada» in the personal portal: the one link of the person reaches marking, «Mi semana» and the keys that mark for
 * them. The key is what marks; a new one comes only from RH's link (docs/superpowers/specs/2026-11-14-worker-access-design.md,
 * decision 3), so the portal link never creates one: it points the person to their entrance, to add a key from a session.
 */
beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-07 15:00:00', 'UTC'));
    $this->tenant = Tenant::factory()->create(['trade_name' => 'Acme', 'slug' => 'acme']);
    workIn($this->tenant);
    $this->ana = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana López']);
    $this->token = resolve(IssuePortalLink::class)->handle($this->ana->id);
});

/** @return array<string, mixed> The `sections` of the home page, keyed by their key. */
function portalHomeSections(string $token): array
{
    $sections = [];
    test()->get(route('portal.show', $token))->assertOk()->assertInertia(function (Assert $page) use (&$sections): void {
        $sections = collect($page->toArray()['props']['sections'])->keyBy('key')->all();
    });

    return $sections;
}

it('lives at /portal/{token}/attendance, outside the panel, throttled like the rest of the portal', function (): void {
    expect(route('portal.attendance.show', 'abc', absolute: false))->toBe('/portal/abc/attendance')
        ->and(Route::getRoutes()->getByName('portal.attendance.show')?->gatherMiddleware())->toContain('throttle:portal-views');
});

describe('the section of the home', function (): void {
    it('is there with a pending step until the person links a phone, and without it afterwards', function (): void {
        $section = portalHomeSections($this->token)['attendance'];

        expect($section['href'])->toBe(route('portal.attendance.show', $this->token))
            ->and($section['label'])->toBe('Mi jornada')
            ->and($section['pending'])->toBe(1);

        WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

        expect(portalHomeSections($this->token)['attendance']['pending'])->toBeNull();
    });

    it('keeps asking while the only phone of the person is revoked, and ignores the phone of somebody else', function (): void {
        $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
        WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $beto->id]);
        WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'revoked_at' => now()]);

        expect(portalHomeSections($this->token)['attendance']['pending'])->toBe(1);
    });

    it('is not there for a company without the registro de jornada', function (): void {
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));

        expect(portalHomeSections($this->token))->not->toHaveKey('attendance');
    });
});

describe('the page', function (): void {
    it('shows the company, the person and the phones that are theirs, with the three ways to go on', function (): void {
        $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
        WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $beto->id, 'label' => 'Teléfono de Beto']);
        WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone de Ana', 'registered_at' => '2026-10-01 12:00:00', 'last_used_at' => '2026-11-06 20:00:00']);
        WorkerCredential::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'Viejo', 'revoked_at' => now()]);

        $response = $this->get(route('portal.attendance.show', $this->token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('attendance/portal')
                ->where('token', $this->token)
                ->where('company', 'Acme')
                ->where('name', 'Ana López')
                ->has('devices', 1)
                ->where('devices.0.label', 'iPhone de Ana')
                ->where('devices.0.enrolledOn', '2026-10-01')
                ->where('devices.0.lastUsedOn', '2026-11-06')
                ->where('markUrl', route('attendance.device.punch.home', 'acme'))
                ->where('weekUrl', route('attendance.device.week.show', 'acme'))
                ->where('keyUrl', route('worker.home', 'acme'))
                ->where('homeUrl', route('portal.show', $this->token)));

        expect(json_encode($response->viewData('page')['props'], JSON_THROW_ON_ERROR))->not->toContain('Beto')->not->toContain('credential');
    });

    it('answers 404 the same for a made-up link, a replaced one, a person who left and a company without the module', function (): void {
        $old = $this->token;
        resolve(IssuePortalLink::class)->handle($this->ana->id, revoke: true);

        $this->get(route('portal.attendance.show', 'x'.str_repeat('0', 63)))->assertNotFound();
        $this->get(route('portal.attendance.show', $old))->assertNotFound();

        $new = resolve(IssuePortalLink::class)->handle($this->ana->id);
        $this->get(route('portal.attendance.show', $new))->assertOk();

        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
        $this->get(route('portal.attendance.show', $new))->assertNotFound();
    });
});

describe('a new key', function (): void {
    it('is never issued from the portal link: the route to link a phone is gone', function (): void {
        expect(Route::has('portal.attendance.enroll'))->toBeFalse();

        $this->post("/portal/{$this->token}/attendance/enroll")->assertNotFound();
    });
});
