<?php

declare(strict_types=1);

use App\Models\AuditEntry;
use App\Modules\Attendance\Actions\IssueSelfEnrollment;
use App\Modules\Attendance\Http\Controllers\PortalController;
use App\Modules\Attendance\Models\AttendanceDevice;
use App\Modules\Attendance\Models\EnrollmentLink;
use App\Modules\Attendance\Support\AttendancePortalSection;
use App\Modules\Telework\Actions\IssuePortalLink;
use App\Modules\Tenancy\Contracts\CompanyModules;
use App\Modules\Tenancy\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Factories\Organization\EmployeeFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

mutates(PortalController::class, IssueSelfEnrollment::class, AttendancePortalSection::class);

/*
 * «Mi jornada» in the personal portal: the one link of the person reaches marking, «Mi semana» and the linking of their
 * phone. The passkey is still what marks; the portal only hands a person who holds their own link (RH never receives it)
 * the same one-use enrollment link RH's email would (docs/superpowers/specs/2026-11-14-single-personal-link-design.md).
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
        ->and(route('portal.attendance.enroll', 'abc', absolute: false))->toBe('/portal/abc/attendance/enroll');

    expect(Route::getRoutes()->getByName('portal.attendance.show')?->gatherMiddleware())->toContain('throttle:portal-views')
        ->and(Route::getRoutes()->getByName('portal.attendance.enroll')?->gatherMiddleware())->toContain('throttle:portal-actions');
});

describe('the section of the home', function (): void {
    it('is there with a pending step until the person links a phone, and without it afterwards', function (): void {
        $section = portalHomeSections($this->token)['attendance'];

        expect($section['href'])->toBe(route('portal.attendance.show', $this->token))
            ->and($section['label'])->toBe('Mi jornada')
            ->and($section['pending'])->toBe(1);

        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id]);

        expect(portalHomeSections($this->token)['attendance']['pending'])->toBeNull();
    });

    it('keeps asking while the only phone of the person is revoked, and ignores the phone of somebody else', function (): void {
        $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);
        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $beto->id]);
        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'revoked_at' => now()]);

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
        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $beto->id, 'label' => 'Teléfono de Beto']);
        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'iPhone de Ana', 'enrolled_at' => '2026-10-01 12:00:00', 'last_used_at' => '2026-11-06 20:00:00']);
        AttendanceDevice::factory()->create(['tenant_id' => $this->tenant->id, 'employee_id' => $this->ana->id, 'label' => 'Viejo', 'revoked_at' => now()]);

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
                ->where('enrollUrl', route('portal.attendance.enroll', $this->token))
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

describe('linking a phone from the portal', function (): void {
    it('issues the person the one-use link of 48 hours and sends them to it, without RH or the audit trail ever holding it', function (): void {
        Log::spy();

        $response = $this->post(route('portal.attendance.enroll', $this->token));

        $link = EnrollmentLink::query()->sole();
        $location = $response->headers->get('Location');

        expect($link->employee_id)->toBe($this->ana->id)
            ->and($link->issued_by)->toBeNull()
            ->and($link->used_at)->toBeNull()
            ->and($link->expires_at->toIso8601String())->toBe(now()->addHours(config()->integer('attendance.enrollment.link_hours'))->toIso8601String())
            ->and($location)->toStartWith(url('/a/acme/enroll/'));

        $token = basename((string) $location);
        expect(EnrollmentLink::findPending($token)?->id)->toBe($link->id)
            ->and(EnrollmentLink::query()->where('token_hash', $token)->exists())->toBeFalse();

        $entry = AuditEntry::query()->where('event', 'attendance.enrollment_issued')->sole();
        expect($entry->metadata)->toMatchArray(['employee_id' => $this->ana->id, 'self_service' => true])
            ->and(json_encode($entry->metadata, JSON_THROW_ON_ERROR))->not->toContain($token)
            ->and($entry->actor_id)->toBeNull();

        $this->get($location)->assertOk()->assertInertia(fn (Assert $page) => $page->component('attendance/enroll')->where('name', 'Ana López'));
    });

    it('replaces the link it issued before: only the last one opens', function (): void {
        $first = basename((string) $this->post(route('portal.attendance.enroll', $this->token))->headers->get('Location'));
        $second = basename((string) $this->post(route('portal.attendance.enroll', $this->token))->headers->get('Location'));

        expect(EnrollmentLink::findPending($first))->toBeNull()
            ->and(EnrollmentLink::findPending($second))->not->toBeNull()
            ->and(EnrollmentLink::query()->count())->toBe(2);
    });

    it('answers 404 for a link that is not valid, a company without the module and a person who left, and issues nothing', function (): void {
        $this->post(route('portal.attendance.enroll', 'x'.str_repeat('0', 63)))->assertNotFound();

        $this->ana->forceFill(['deactivated_at' => now()])->save();
        $this->post(route('portal.attendance.enroll', $this->token))->assertNotFound();

        $this->ana->forceFill(['deactivated_at' => null])->save();
        resolve(CompanyModules::class)->syncPlan($this->tenant->id, ['vacations'], CarbonImmutable::now(config('app.display_timezone')));
        $this->post(route('portal.attendance.enroll', $this->token))->assertNotFound();

        expect(EnrollmentLink::query()->count())->toBe(0);
    });

    it('never issues a link for somebody else than the owner of the portal link', function (): void {
        $beto = EmployeeFactory::new()->create(['tenant_id' => $this->tenant->id]);

        $this->post(route('portal.attendance.enroll', $this->token), ['employee_id' => $beto->id])->assertRedirect();

        expect(EnrollmentLink::query()->sole()->employee_id)->toBe($this->ana->id);
    });
});
